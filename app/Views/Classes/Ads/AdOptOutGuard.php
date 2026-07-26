<?php

namespace App\Views\Ads;

use App\Services\Ads\AdOptOutService;

/**
 * 広告オプトアウトのクライアント側ガード（難読化インラインJSをHTMLに出力する）
 *
 * ## 何をするものか
 *
 * `/admin/disable-ads` で合言葉を入れた人のブラウザには、サーバでしか作れないトークンが
 * クッキーで入っている。このガードはそのクッキーを **同期で** 検証し、正しければ
 * そのページの広告コードを一切実行させない。
 *
 * サイトのページは Cloudflare の Cache Everything でキャッシュされるため、サーバが返す HTML は
 * 全訪問者で同一である必要がある（詳細は AdOptOutService のコメント）。したがって判定は
 * クライアント JS でやるしかなく、ページに置けるのは「トークンの sha256」だけ。
 * 逆算できないので、ソースを読んでもクッキーの正解値は分からない。
 *
 * ## 止め方（ここが肝）
 *
 * 先行するインラインJSから、後続の `<script src=...>` を DOM 操作で止めることは仕様上できない
 * （DOM に挿入された時点で実行が確定する）。`window.adsbygoogle` を潰す方法も自動広告が別経路で
 * 走るため漏れる。確実に止められるのは **「広告タグを不活性なプレースホルダとして出力し、
 * JS が昇格させたときだけ本物の script になる」** 方式だけなので、それを採る
 * （GoogleAdsense::gTag が `scriptTag()` を使う）。
 *
 * ## フェイルオープン（収益を守るための最重要方針）
 *
 * グローバルフラグは **「広告オフのとき true」** で、昇格側は `if (フラグ) 何もしない` と書く。
 * こうしておくと、このガードが例外・構文エラー・配信事故などで一切動かなかった場合フラグは
 * undefined になり、**全ユーザーで広告は今まで通り表示される**。判定不能で広告が消える
 * （＝収益がゼロになる）事故が起きない向きに倒してある。
 *
 * ## 難読化
 *
 * 既に実績のある ad_guard.php と同じ手法（識別子の乱数化・機微な文字列の XOR エンコード）。
 * ページは CDN キャッシュされるのでリクエスト単位ではなく「キャッシュ生成単位」で回転する。
 */
class AdOptOutGuard
{
    /** ガード本体（判定＋フラグ設定＋CSS）を出力済みか */
    private static bool $rendered = false;

    /** 乱数識別子（生成単位。JS のグローバル名・関数名・CSSクラス名） */
    private static ?array $names = null;

    /** 文字列エンコード用の XOR 鍵（生成単位） */
    private static array $keyBytes = [];

    /**
     * この環境でオプトアウト機能を使うか
     *
     * secrets が未設定の環境では何も出力せず、広告まわりの挙動は従来と完全に同一になる。
     */
    public static function isEnabled(): bool
    {
        return AdOptOutService::isConfigured();
    }

    /**
     * 乱数識別子と XOR 鍵を用意する（1回だけ）
     */
    private static function init(): void
    {
        if (self::$names !== null) {
            return;
        }

        $rid = static fn(): string => 'z' . substr(bin2hex(random_bytes(8)), 0, 10);

        self::$names = [];
        foreach (['flag', 'dec', 'key', 'hash', 'cookie', 'cls', 'K', 'H', 'pdec', 'pkey'] as $k) {
            self::$names[$k] = $rid();
        }

        self::$keyBytes = array_values(unpack('C*', random_bytes(32)));
    }

    /**
     * 文字列を XOR エンコードし、実行時に復号する JS 式を返す（配信HTMLに平文を残さない）
     *
     * @param ?string $decFn 復号関数の名前。null なら本体ガードの復号関数を使う。
     */
    private static function enc(string $s, ?string $decFn = null): string
    {
        self::init();

        $bytes = array_values(unpack('C*', $s));
        $keyLen = count(self::$keyBytes);
        $out = [];
        foreach ($bytes as $i => $b) {
            $out[] = $b ^ self::$keyBytes[$i % $keyLen];
        }

        return ($decFn ?? self::$names['dec']) . '([' . implode(',', $out) . '])';
    }

    /**
     * 「広告オフ」を表すグローバル変数名（true のとき広告を出さない）
     */
    public static function flagVar(): string
    {
        self::init();
        return self::$names['flag'];
    }

    /**
     * ガード本体を出力する（何度呼んでも実際の出力は1回だけ）
     *
     * 広告に関わる出力（gTag / loadAdsTag / output / ad_guard）の前に必ず通るよう、
     * それぞれの入口から呼ぶ。
     */
    public static function render(): void
    {
        if (self::$rendered || !self::isEnabled()) {
            return;
        }

        self::$rendered = true;
        self::init();

        $N = self::$names;
        $keyJs = '[' . implode(',', self::$keyBytes) . ']';
        $cookieEnc = self::enc(AdOptOutService::cookieName() . '=');
        $hashEnc = self::enc(AdOptOutService::pageHash());
        $nonce = bin2hex(random_bytes(8));

        // 広告枠の畳み込み。:has() 非対応ブラウザでルール全体が捨てられないよう、
        // :has() を含むセレクタは必ず別ルールに分ける（カンマ区切りに混ぜると全体が無効になる）。
        echo <<<EOT
        <style>html.{$N['cls']} ins.adsbygoogle,html.{$N['cls']} ins.adsbygoogle-noablate,html.{$N['cls']} .google-auto-placed{display:none !important}</style>
        <style>html.{$N['cls']} div:has(> ins.adsbygoogle){display:none !important}</style>
        EOT;

        // SHA-256（同期・自前実装）。crypto.subtle は非同期で昇格を遅らせるうえ、
        // 呼び出し自体が分かりやすい手がかりになるので使わない。
        echo <<<EOT
        <script>/* {$nonce} */(function(){
          try{
            var {$N['key']}={$keyJs};
            function {$N['dec']}(a){var b=new Uint8Array(a.length);for(var i=0;i<a.length;i++){b[i]=a[i]^{$N['key']}[i%{$N['key']}.length];}return new TextDecoder().decode(b);}
            var {$N['K']}=[1116352408,1899447441,3049323471,3921009573,961987163,1508970993,2453635748,2870763221,3624381080,310598401,607225278,1426881987,1925078388,2162078206,2614888103,3248222580,3835390401,4022224774,264347078,604807628,770255983,1249150122,1555081692,1996064986,2554220882,2821834349,2952996808,3210313671,3336571891,3584528711,113926993,338241895,666307205,773529912,1294757372,1396182291,1695183700,1986661051,2177026350,2456956037,2730485921,2820302411,3259730800,3345764771,3516065817,3600352804,4094571909,275423344,430227734,506948616,659060556,883997877,958139571,1322822218,1537002063,1747873779,1955562222,2024104815,2227730452,2361852424,2428436474,2756734187,3204031479,3329325298];
            function {$N['hash']}(m){
              var {$N['H']}=[1779033703,3144134277,1013904242,2773480762,1359893119,2600822924,528734635,1541459225];
              var b=[],i,t;
              for(i=0;i<m.length;i++){b.push(m.charCodeAt(i)&255);}
              var bl=b.length*8;b.push(128);
              while(b.length%64!==56){b.push(0);}
              b.push(0,0,0,0,(bl>>>24)&255,(bl>>>16)&255,(bl>>>8)&255,bl&255);
              var w=new Array(64);
              for(var o=0;o<b.length;o+=64){
                for(t=0;t<16;t++){w[t]=(b[o+t*4]<<24)|(b[o+t*4+1]<<16)|(b[o+t*4+2]<<8)|b[o+t*4+3];}
                for(t=16;t<64;t++){var x=w[t-15],y=w[t-2];var s0=((x>>>7)|(x<<25))^((x>>>18)|(x<<14))^(x>>>3);var s1=((y>>>17)|(y<<15))^((y>>>19)|(y<<13))^(y>>>10);w[t]=(w[t-16]+s0+w[t-7]+s1)|0;}
                var a={$N['H']}[0],bb={$N['H']}[1],c={$N['H']}[2],d={$N['H']}[3],e={$N['H']}[4],f={$N['H']}[5],g={$N['H']}[6],h={$N['H']}[7];
                for(t=0;t<64;t++){
                  var S1=((e>>>6)|(e<<26))^((e>>>11)|(e<<21))^((e>>>25)|(e<<7));
                  var ch=(e&f)^(~e&g);
                  var t1=(h+S1+ch+{$N['K']}[t]+w[t])|0;
                  var S0=((a>>>2)|(a<<30))^((a>>>13)|(a<<19))^((a>>>22)|(a<<10));
                  var mj=(a&bb)^(a&c)^(bb&c);
                  var t2=(S0+mj)|0;
                  h=g;g=f;f=e;e=(d+t1)|0;d=c;c=bb;bb=a;a=(t1+t2)|0;
                }
                {$N['H']}[0]=({$N['H']}[0]+a)|0;{$N['H']}[1]=({$N['H']}[1]+bb)|0;{$N['H']}[2]=({$N['H']}[2]+c)|0;{$N['H']}[3]=({$N['H']}[3]+d)|0;
                {$N['H']}[4]=({$N['H']}[4]+e)|0;{$N['H']}[5]=({$N['H']}[5]+f)|0;{$N['H']}[6]=({$N['H']}[6]+g)|0;{$N['H']}[7]=({$N['H']}[7]+h)|0;
              }
              var r='';
              for(i=0;i<8;i++){r+=('00000000'+({$N['H']}[i]>>>0).toString(16)).slice(-8);}
              return r;
            }
            var {$N['cookie']}={$cookieEnc};
            var p=document.cookie.split('; '),v='';
            for(var i=0;i<p.length;i++){if(p[i].indexOf({$N['cookie']})===0){v=p[i].slice({$N['cookie']}.length);break;}}
            if(v&&{$N['hash']}(v)==={$hashEnc}){
              window.{$N['flag']}=true;
              document.documentElement.className+=' {$N['cls']}';
            }
          }catch(e){}
        })();</script>
        EOT;
    }

    /**
     * adsbygoogle.js を「不活性プレースホルダ＋JSによる昇格」の形で出力する
     *
     * オプトアウトしていない普通の訪問者では、この直後の同期スクリプトが即座に本物の
     * `<script async src=...>` を作って head に挿す（＝従来と同じタイミングで読み込まれる）。
     * オプトアウト済みなら昇格しないので、Google へのリクエストが一切発生しない。
     *
     * @param string $src adsbygoogle.js の URL
     * @param string $id 生成する script 要素の id（ad_guard が存在確認に使う）
     * @param ?string $dataOverlays data-overlays 属性の値（不要なら null）
     */
    public static function scriptTag(string $src, string $id, ?string $dataOverlays = null): string
    {
        self::render();

        // 機能が無効な環境（secrets 未設定）では従来どおりの素の script タグを返す
        if (!self::isEnabled()) {
            $attr = $dataOverlays ? ('data-overlays="' . $dataOverlays . '" ') : '';
            return '<script async ' . $attr . 'id="' . $id . '" src="' . $src . '" crossorigin="anonymous"></script>';
        }

        $N = self::$names;
        $dec = $N['pdec'];

        // 重要: この昇格スクリプトは本体ガードに一切依存させない（自前の復号関数を持たせる）。
        // 本体ガードの中の関数を参照すると、ガードが何らかの理由で動かなかったときに
        // ここが ReferenceError で落ち、全ユーザーの広告が消える。フラグは window 経由で
        // 読むだけなので、undefined（＝ガード未実行）でも安全に「広告を出す」に倒れる。
        // 空文字を「属性なし」とみなすのは従来の出力（$dataOverlays ? ... : ''）と揃えるため
        $overlaysJs = $dataOverlays
            ? 's.setAttribute(' . self::enc('data-overlays', $dec) . ',' . self::enc($dataOverlays, $dec) . ');'
            : '';

        $srcEnc = self::enc($src, $dec);
        $idEnc = self::enc($id, $dec);
        $keyJs = '[' . implode(',', self::$keyBytes) . ']';

        return <<<EOT
        <script>(function(){
          if(window.{$N['flag']}){return;}
          var {$N['pkey']}={$keyJs};
          function {$dec}(a){var b=new Uint8Array(a.length);for(var i=0;i<a.length;i++){b[i]=a[i]^{$N['pkey']}[i%{$N['pkey']}.length];}return new TextDecoder().decode(b);}
          var s=document.createElement('script');
          s.async=true;
          s.id={$idEnc};
          s.crossOrigin='anonymous';
          {$overlaysJs}
          s.src={$srcEnc};
          (document.head||document.documentElement).appendChild(s);
        })();</script>
        EOT;
    }
}
