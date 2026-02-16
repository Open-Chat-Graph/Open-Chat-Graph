.PHONY: help init init-y init-y-n _init up down restart rebuild ssh up-mock cron cron-stop show cert ci-test build-frontend build-frontend\:ranking build-frontend\:comments build-frontend\:graph build-frontend\:all-room-stats _build-one-frontend _wait-mysql _is-mock _check-data-protection

# .envファイルを読み込み（存在しない場合はスキップ）
-include .env

# デフォルト値（.envで定義されていない場合に使用）
HTTPS_PORT ?= 8443
WEB_PORT ?= 8000
PHP_MY_ADMIN_PORT ?= 8080
LINE_MOCK_PORT ?= 9000
MYSQL_PORT ?= 3306
DATA_PROTECTION ?= false

# データ保護チェック（内部用ヘルパー）
_check-data-protection:
	@if [ "$(DATA_PROTECTION)" = "true" ]; then \
		echo "$(RED)エラー: データ保護モードが有効です$(NC)"; \
		echo "$(YELLOW)このコマンドは DATA_PROTECTION=true の環境では実行できません$(NC)"; \
		echo "$(YELLOW)無効にするには .env の DATA_PROTECTION を false に変更してください$(NC)"; \
		exit 1; \
	fi

# カラー定義
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m

# Mock環境かどうかを判定
_is-mock:
	@docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q .

# MySQLの準備を待機（内部用ヘルパー）
_wait-mysql:
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		if docker compose exec -T mysql mysqladmin ping -uroot -ptest_root_pass --silent 2>/dev/null; then \
			break; \
		fi; \
		sleep 2; \
	done

help: ## ヘルプを表示
	@echo "$(GREEN)利用可能なコマンド:$(NC)"
	@echo ""
	@echo "$(YELLOW)基本環境:$(NC)"
	@echo "  $(GREEN)make up$(NC)          - 基本環境を起動"
	@echo "  $(GREEN)make up-cron$(NC)     - 基本環境を起動（Cron自動実行モード）"
	@echo "  $(GREEN)make down$(NC)        - 環境を停止（基本・Mock両対応）"
	@echo "  $(GREEN)make restart$(NC)     - 環境を再起動（基本・Mock自動判定）"
	@echo "  $(GREEN)make rebuild$(NC)     - 環境を再ビルド（基本・Mock自動判定）"
	@echo "  $(GREEN)make ssh$(NC)         - コンテナにログイン（基本・Mock両対応）"
	@echo ""
	@echo "$(YELLOW)Mock付き環境:$(NC)"
	@echo "  $(GREEN)make up-mock$(NC)     - Mock環境を起動（docker/line-mock-api/.env.mock の設定を使用）"
	@echo ""
	@echo "$(YELLOW)Cron管理:$(NC)"
	@echo "  $(GREEN)make cron$(NC)          - Cronを有効化（毎時30/35/40分に自動クローリング）"
	@echo "  $(GREEN)make cron-stop$(NC)     - Cronを無効化"
	@echo ""
	@echo "$(YELLOW)フロントエンド:$(NC)"
	@echo "  $(GREEN)make build-frontend$(NC)          - フロントエンドを全てビルド"
	@echo "  $(GREEN)make build-frontend:ranking$(NC)  - ランキングのみビルド"
	@echo "  $(GREEN)make build-frontend:comments$(NC) - コメントのみビルド"
	@echo "  $(GREEN)make build-frontend:graph$(NC)    - グラフのみビルド"
	@echo "  $(GREEN)make build-frontend:all-room-stats$(NC) - 全体統計のみビルド"
	@echo ""
	@echo "$(YELLOW)その他:$(NC)"
	@echo "  $(GREEN)make show$(NC)        - 現在の起動モードを表示"
	@echo "  $(GREEN)make cert$(NC)        - SSL証明書を更新（LAN内ホスト/IPを追加可能）"
	@echo "  $(GREEN)make init$(NC)        - SSL証明書生成 + 環境初期化"
	@echo "  $(GREEN)make init-y$(NC)      - 確認なしで初期化"
	@echo "  $(GREEN)make init-y-n$(NC)    - 確認なしで初期化（local-secrets.phpは保持）"
	@echo "  $(GREEN)make ci-test$(NC)     - CIテストを実行（Mock環境でクローリング+URLテスト）"

init: _check-data-protection ## 初回セットアップ
	@$(MAKE) _init ARGS=""

init-y: _check-data-protection ## 初回セットアップ（確認なし）
	@$(MAKE) _init ARGS="-y"

init-y-n: _check-data-protection ## 初回セットアップ（確認なし、local-secrets.phpは保持）
	@$(MAKE) _init ARGS="-y -n"

_init:
	@echo "$(GREEN)初回セットアップを開始します...$(NC)"
	@# .envファイルがない場合は.env.exampleからコピー
	@if [ ! -f .env ]; then \
		if [ -f .env.example ]; then \
			echo "$(YELLOW).envファイルが存在しません。.env.exampleからコピーします...$(NC)"; \
			cp .env.example .env; \
			echo "$(GREEN).envファイルを作成しました。必要に応じて編集してください$(NC)"; \
		else \
			echo "$(YELLOW)警告: .env.exampleが見つかりません$(NC)"; \
		fi; \
	else \
		echo "$(GREEN).envファイルは既に存在します$(NC)"; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@# コンテナが停止していれば起動、セットアップ後に停止
	@CONTAINERS_WERE_STOPPED=0; \
	if ! docker compose ps mysql 2>/dev/null | grep -q "Up" || ! docker compose ps app 2>/dev/null | grep -q "Up"; then \
		echo "$(YELLOW)コンテナを起動します...$(NC)"; \
		docker compose up -d mysql app; \
		echo "$(YELLOW)MySQLの起動を待機しています...$(NC)"; \
		$(MAKE) _wait-mysql; \
		echo "$(GREEN)MySQLが起動しました$(NC)"; \
		CONTAINERS_WERE_STOPPED=1; \
	else \
		echo "$(GREEN)コンテナはすでに起動しています$(NC)"; \
	fi; \
	if [ ! -f local-secrets.php ] || [ -n "$(ARGS)" ]; then \
		if [ ! -f local-secrets.php ]; then \
			echo "$(YELLOW)local-secrets.phpが存在しません。セットアップスクリプトを実行します$(NC)"; \
		fi; \
		if [ -f local-setup.sh ]; then \
			./local-setup.sh $(ARGS); \
		else \
			./setup/local-setup.default.sh $(ARGS); \
		fi; \
	fi; \
	if [ $$CONTAINERS_WERE_STOPPED -eq 1 ]; then \
		echo "$(YELLOW)コンテナを停止します...$(NC)"; \
		docker compose --profile dev down; \
		echo "$(GREEN)コンテナを停止しました$(NC)"; \
	fi
	@$(MAKE) build-frontend
	@echo "$(GREEN)初回セットアップが完了しました$(NC)"

# フロントエンドビルド
# 使い方: make build-frontend (全て) / make build-frontend:ranking / make build-frontend:comments / make build-frontend:graph
_build-one-frontend:
	@if [ -f "$(DIR)/package.json" ]; then \
		echo "  $(YELLOW)ビルド: $(DIR)$(NC)"; \
		(cd "$(DIR)" && npm install && npm run build); \
	else \
		echo "$(RED)エラー: $(DIR)/package.json が見つかりません$(NC)"; \
		exit 1; \
	fi

build-frontend: ## フロントエンドを全てビルド（frontend/*/）
	@echo "$(GREEN)フロントエンドをビルドしています...$(NC)"
	@for dir in frontend/*/; do \
		if [ -f "$$dir/package.json" ]; then \
			echo "  $(YELLOW)ビルド: $$dir$(NC)"; \
			(cd "$$dir" && npm install && npm run build); \
		fi; \
	done
	@echo "$(GREEN)フロントエンドビルド完了$(NC)"

build-frontend\:ranking: ## ランキングのみビルド
	@echo "$(GREEN)ranking をビルドしています...$(NC)"
	@$(MAKE) _build-one-frontend DIR=frontend/ranking
	@echo "$(GREEN)ranking ビルド完了$(NC)"

build-frontend\:comments: ## コメントのみビルド
	@echo "$(GREEN)comments をビルドしています...$(NC)"
	@$(MAKE) _build-one-frontend DIR=frontend/comments
	@echo "$(GREEN)comments ビルド完了$(NC)"

build-frontend\:graph: ## グラフのみビルド
	@echo "$(GREEN)graph をビルドしています...$(NC)"
	@$(MAKE) _build-one-frontend DIR=frontend/stats-graph
	@echo "$(GREEN)graph ビルド完了$(NC)"

build-frontend\:all-room-stats: ## 全体統計のみビルド
	@echo "$(GREEN)all-room-stats をビルドしています...$(NC)"
	@$(MAKE) _build-one-frontend DIR=frontend/all-room-stats
	@echo "$(GREEN)all-room-stats ビルド完了$(NC)"

# 基本環境
up: ## 基本環境を起動
	@if docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q .; then \
		echo "$(YELLOW)Mock環境から基本環境に切り替えています...$(NC)"; \
		docker compose --profile dev -f docker-compose.yml -f docker-compose.mock.yml down; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)基本環境を起動しています...$(NC)"
	@IS_MOCK_ENVIRONMENT=0 CRON=0 docker compose --profile dev up -d --no-deps --force-recreate app && IS_MOCK_ENVIRONMENT=0 CRON=0 docker compose --profile dev up -d
	@echo "$(GREEN)基本環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:${HTTPS_PORT}"
	@echo "  phpMyAdmin: http://localhost:${PHP_MY_ADMIN_PORT}"

down: ## 環境を停止（基本・Mock両対応）
	@echo "$(RED)環境を停止しています...$(NC)"
	@if docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q .; then \
		echo "$(YELLOW)Mock環境を検出しました$(NC)"; \
		docker compose --profile dev -f docker-compose.yml -f docker-compose.mock.yml down; \
	else \
		docker compose --profile dev down; \
	fi
	@echo "$(RED)環境が停止しました$(NC)"

restart: down ## 環境を再起動（基本・Mock自動判定）
	@if [ -f docker/line-mock-api/.env.mock ]; then \
		echo "$(YELLOW)docker/line-mock-api/.env.mockが存在します。Mock環境として再起動します$(NC)"; \
		$(MAKE) up-mock; \
	else \
		echo "$(YELLOW)基本環境として再起動します$(NC)"; \
		$(MAKE) up; \
	fi

rebuild: down ## 環境を再ビルド（基本・Mock自動判定）
	@if docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q . || [ -f docker/line-mock-api/.env.mock ]; then \
		echo "$(GREEN)Mock環境をビルドしています...$(NC)"; \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml build; \
		echo "$(GREEN)ビルドが完了しました$(NC)"; \
		$(MAKE) up-mock; \
	else \
		echo "$(GREEN)基本環境をビルドしています...$(NC)"; \
		docker compose build; \
		echo "$(GREEN)ビルドが完了しました$(NC)"; \
		$(MAKE) up; \
	fi

ssh: ## コンテナにログイン（基本・Mock両対応）
	@if $(MAKE) _is-mock 2>/dev/null; then \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml exec app bash; \
	else \
		docker compose exec app bash; \
	fi

# Mock付き環境
up-mock: _check-data-protection ## Mock付き環境を起動（docker/line-mock-api/.env.mockの設定を使用）
	@if docker compose ps -a -q mysql 2>/dev/null | grep -q . && ! docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q .; then \
		echo "$(YELLOW)基本環境からMock環境に切り替えています...$(NC)"; \
		$(MAKE) down; \
	fi
	@if [ ! -f docker/line-mock-api/.env.mock ]; then \
		echo "$(RED)docker/line-mock-api/.env.mockが見つかりません$(NC)"; \
		echo "$(YELLOW)docker/line-mock-api/.env.mock.exampleからdocker/line-mock-api/.env.mockを作成します...$(NC)"; \
		cp docker/line-mock-api/.env.mock.example docker/line-mock-api/.env.mock; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています...$(NC)"
	@echo "$(YELLOW)docker/line-mock-api/.env.mockの設定:$(NC)"
	@cat docker/line-mock-api/.env.mock | grep -v "^#" | grep -v "^$$" | sed 's/^/  /'
	@export $$(cat docker/line-mock-api/.env.mock | grep -v "^#" | xargs) && \
		IS_MOCK_ENVIRONMENT=1 CRON=0 docker compose --profile dev -f docker-compose.yml -f docker-compose.mock.yml up -d --no-deps --force-recreate app line-mock-api && \
		IS_MOCK_ENVIRONMENT=1 CRON=0 docker compose --profile dev -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:${HTTPS_PORT} (基本環境)"
	@echo "  phpMyAdmin: http://localhost:${PHP_MY_ADMIN_PORT}"
	@echo "  LINE Mock API: http://localhost:${LINE_MOCK_PORT}"
	@echo ""
	@echo "$(YELLOW)設定変更:$(NC) docker/line-mock-api/.env.mockを編集して make restart"

# Cron管理
cron: ## Cronを有効化（毎時30/35/40分に自動クローリング）
	@if ! docker compose ps -a -q app 2>/dev/null | grep -q .; then \
		echo "$(RED)コンテナが起動していません$(NC)"; \
		echo "$(YELLOW)まず 'make up' または 'make up-mock' を実行してください$(NC)"; \
		exit 1; \
	fi
	@echo "$(GREEN)Cronを有効化しています...$(NC)"
	@if $(MAKE) _is-mock 2>/dev/null; then \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml cp docker/app/openchat-crawling.cron app:/etc/cron.d/openchat-crawling; \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T -u root app bash -c 'chmod 644 /etc/cron.d/openchat-crawling && cron'; \
	else \
		docker compose cp docker/app/openchat-crawling.cron app:/etc/cron.d/openchat-crawling; \
		docker compose exec -T -u root app bash -c 'chmod 644 /etc/cron.d/openchat-crawling && cron'; \
	fi
	@echo "$(GREEN)Cronが有効化されました$(NC)"
	@echo "$(YELLOW)スケジュール: 30分(日本語) | 35分(繁体字) | 40分(タイ語)$(NC)"
	@$(MAKE) show

cron-stop: ## Cronを無効化
	@if ! docker compose ps -a -q app 2>/dev/null | grep -q .; then \
		echo "$(RED)コンテナが起動していません$(NC)"; \
		exit 1; \
	fi
	@echo "$(YELLOW)Cronを無効化しています...$(NC)"
	@if $(MAKE) _is-mock 2>/dev/null; then \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T -u root app bash -c 'rm -f /etc/cron.d/openchat-crawling && pkill cron || true'; \
	else \
		docker compose exec -T -u root app bash -c 'rm -f /etc/cron.d/openchat-crawling && pkill cron || true'; \
	fi
	@echo "$(GREEN)Cronが無効化されました$(NC)"
	@$(MAKE) show

show: ## 現在の起動モードを表示
	@echo "$(GREEN)========================================$(NC)"
	@echo "$(GREEN)  現在の起動モード$(NC)"
	@echo "$(GREEN)========================================$(NC)"
	@if ! docker compose ps -a -q app 2>/dev/null | grep -q .; then \
		echo "$(RED)コンテナが起動していません$(NC)"; \
		echo ""; \
		echo "$(YELLOW)利用可能なコマンド:$(NC) make up | make up-mock"; \
	else \
		IS_MOCK=$$(docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q . && echo "1" || echo "0"); \
		DC_CMD=$$([ "$$IS_MOCK" = "1" ] && echo "docker compose --profile dev -f docker-compose.yml -f docker-compose.mock.yml" || echo "docker compose --profile dev"); \
		echo "$(YELLOW)環境:$(NC) $$([ "$$IS_MOCK" = "1" ] && echo "Mock付き" || echo "基本")"; \
		echo "$(YELLOW)起動中:$(NC)"; \
		$$DC_CMD ps -a --format '  {{.Name}}'; \
		echo ""; \
		if [ "$$IS_MOCK" = "1" ] && [ -f docker/line-mock-api/.env.mock ]; then \
			echo "$(YELLOW)docker/line-mock-api/.env.mock:$(NC)"; \
			cat docker/line-mock-api/.env.mock | grep -v "^#" | grep -v "^$$" | sed 's/^/  /'; \
			echo ""; \
		fi; \
		echo "$(YELLOW)環境変数:$(NC)"; \
		$$DC_CMD exec -T app sh -c 'echo "  IS_MOCK=$$IS_MOCK_ENVIRONMENT CRON=$$CRON"' 2>/dev/null || echo "  (取得失敗)"; \
		CRON_STATUS=$$($$DC_CMD exec -T app sh -c '[ -f /etc/cron.d/openchat-crawling ] && echo "有効" || echo "無効"' 2>/dev/null); \
		echo "$(YELLOW)Cron:$(NC) $$CRON_STATUS"; \
	fi
	@echo "$(GREEN)========================================$(NC)"

cert: ## SSL証明書を更新（LAN内ホスト/IPを追加可能）
	@./docker/app/generate-ssl-certs.sh --force
	@echo ""
	@if docker compose ps -q app 2>/dev/null | grep -q .; then \
		echo "$(YELLOW)Apacheを再読み込みして証明書を反映します...$(NC)"; \
		if docker ps -a --filter "name=line-mock-api" --format "{{.Names}}" | grep -q .; then \
			docker compose -f docker-compose.yml -f docker-compose.mock.yml exec app apachectl graceful; \
		else \
			docker compose exec app apachectl graceful; \
		fi; \
		echo "$(GREEN)証明書の更新が完了しました$(NC)"; \
	else \
		echo "$(YELLOW)appコンテナが起動していないため、次回起動時に新しい証明書が使用されます$(NC)"; \
	fi

ci-test: _check-data-protection ## ローカルでCIテストを実行（Mock環境でクローリング+URLテスト）
	@echo "$(GREEN)========================================"
	@echo "  ローカルCIテスト開始"
	@echo "========================================$(NC)"
	@echo "$(YELLOW)[1/4] Mock環境を起動...$(NC)"
	@$(MAKE) up-mock > /dev/null 2>&1 || $(MAKE) up-mock
	@echo "$(YELLOW)[2/4] サービス準備を待機...$(NC)"
	@$(MAKE) _wait-mysql
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T app php -v > /dev/null 2>&1 && break; \
		sleep 2; \
	done
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		curl -k -s http://localhost:9000 > /dev/null 2>&1 && break; \
		sleep 2; \
	done
	@echo "$(GREEN)✓ 準備完了$(NC)"
	@echo "$(YELLOW)[3/4] 環境を初期化...$(NC)"
	@$(MAKE) init-y-n > /dev/null 2>&1
	@echo "$(GREEN)✓ 初期化完了$(NC)"
	@echo "$(YELLOW)[4/4] テストを実行...$(NC)"
	@chmod +x ./.github/scripts/test-ci.sh ./.github/scripts/test-urls.sh ./.github/scripts/check-error-log.sh
	@./.github/scripts/test-ci.sh -y
	@./.github/scripts/test-urls.sh && ./.github/scripts/check-error-log.sh
	@echo "$(GREEN)========================================"
	@echo "  ローカルCIテスト完了"
	@echo "========================================$(NC)"
