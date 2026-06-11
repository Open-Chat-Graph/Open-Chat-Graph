# データAPI オーナー向けメモ

公開用の利用ガイドは [`API_README.md`](API_README.md)。こちらは運用側の覚書。

## APIユーザーの追加方法

APIにログインできるユーザーは `app/Config/ApiUser.php`（gitignore）の `ApiUser::$apiUser` 配列で定義する。`username` / `password` を追加する。

- URLパスの `{username}` と Basic認証の `user` / `pass` の3つが一致したときだけ通る（`app/Config/routing.php` の `$databaseApiAuth`）。
- ここに含まれる `username` のみログイン可。未登録の `username` でアクセスすると 403 `User not found`。
- `ApiUser` クラス自体が存在しない（未配置）場合は「登録ユーザーなし＝全ログイン失敗」にフォールバックする。

## レートリミット

`/database/{username}/query` にはユーザー単位のレートリミットがかかる（`app/Services/Api/DatabaseApiRateLimiter.php`）。

- 同時実行は1リクエストまで（2件目以降は 429）
- 直近5分間で取得できるレコード数は合計1000件まで（超過は 429 + `Retry-After`）
- 上限値はクラスの定数 `WINDOW_SECONDS` / `MAX_RECORDS_PER_WINDOW` で変更する
- 状態は `storage/api_rate_limit/`（gitignore済・ユーザー名のSHA256がファイル名）に保存。消せばリセットされる
- `{username}` が `adminApiKey` の場合はレートリミット対象外

```php
namespace App\Config;

class ApiUser
{
    /** @var array<int, array{username:string, password:string}> */
    static array $apiUser = [
        [
            'username' => 'user1',
            'password' => 'password',
        ],
        [
            'username' => 'user2',
            'password' => 'password',
        ],
    ];
}
```
