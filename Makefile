.PHONY: help init init-y init-y-n _init up up-cron down restart rebuild ssh up-mock up-mock-slow up-mock-cron up-mock-slow-cron down-mock restart-mock restart-mock-slow restart-mock-cron rebuild-mock ssh-mock show

# カラー定義
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m

help: ## ヘルプを表示
	@echo "$(GREEN)利用可能なコマンド:$(NC)"
	@echo ""
	@echo "$(YELLOW)基本環境:$(NC)"
	@echo "  $(GREEN)make up$(NC)          - 基本環境を起動"
	@echo "  $(GREEN)make up-cron$(NC)     - 基本環境を起動（Cron自動実行モード）"
	@echo "  $(GREEN)make down$(NC)        - 環境を停止（基本・Mock両対応）"
	@echo "  $(GREEN)make restart$(NC)     - 基本環境を再起動"
	@echo "  $(GREEN)make rebuild$(NC)     - 基本環境を再ビルドして起動"
	@echo "  $(GREEN)make ssh$(NC)         - コンテナにログイン（基本・Mock両対応）"
	@echo ""
	@echo "$(YELLOW)Mock付き環境:$(NC)"
	@echo "  $(GREEN)make up-mock$(NC)               - Mock環境（1万件、遅延なし）"
	@echo "  $(GREEN)make up-mock-slow$(NC)          - Mock環境（10万件、本番並み遅延）"
	@echo "  $(GREEN)make up-mock-cron$(NC)          - Mock環境（1万件、Cron自動実行）"
	@echo "  $(GREEN)make up-mock-slow-cron$(NC)     - Mock環境（10万件、遅延+Cron）"
	@echo "  $(GREEN)make restart-mock$(NC)          - Mock環境を再起動（1万件）"
	@echo "  $(GREEN)make restart-mock-slow$(NC)     - Mock環境を再起動（10万件）"
	@echo "  $(GREEN)make restart-mock-cron$(NC)     - Mock環境を再起動（Cron）"
	@echo "  $(GREEN)make rebuild-mock$(NC)          - Mock環境を再ビルドして起動"
	@echo ""
	@echo "$(YELLOW)その他:$(NC)"
	@echo "  $(GREEN)make show$(NC)        - 現在の起動モードを表示"
	@echo "  $(GREEN)make init$(NC)        - SSL証明書生成 + 環境初期化"
	@echo "  $(GREEN)make init-y$(NC)      - 確認なしで初期化"
	@echo "  $(GREEN)make init-y-n$(NC)    - 確認なしで初期化（local-secrets.phpは保持）"

init: ## 初回セットアップ
	@$(MAKE) _init ARGS=""

init-y: ## 初回セットアップ（確認なし）
	@$(MAKE) _init ARGS="-y"

init-y-n: ## 初回セットアップ（確認なし、local-secrets.phpは保持）
	@$(MAKE) _init ARGS="-y -n"

_init:
	@echo "$(GREEN)初回セットアップを開始します...$(NC)"
	@./docker/app/generate-ssl-certs.sh
	@./docker/line-mock-api/generate-ssl-certs.sh
	@# コンテナが停止していれば起動、セットアップ後に停止（冪等性）
	@CONTAINERS_WERE_STOPPED=0; \
	if ! docker compose ps mysql 2>/dev/null | grep -q "Up" || ! docker compose ps app 2>/dev/null | grep -q "Up"; then \
		echo "$(YELLOW)コンテナを起動します...$(NC)"; \
		docker compose up -d mysql app; \
		echo "$(YELLOW)MySQLの起動を待機しています...$(NC)"; \
		for i in 1 2 3 4 5 6 7 8 9 10; do \
			if docker compose exec -T mysql mysqladmin ping -uroot -ptest_root_pass --silent 2>/dev/null; then \
				echo "$(GREEN)MySQLが起動しました$(NC)"; \
				break; \
			fi; \
			sleep 1; \
		done; \
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
		docker compose down; \
		echo "$(GREEN)コンテナを停止しました$(NC)"; \
	fi
	@echo "$(GREEN)初回セットアップが完了しました$(NC)"

# 基本環境
up: ## 基本環境を起動
	@if docker network ls | grep -q oc-review-mock_mock-network; then \
		echo "$(YELLOW)Mock環境から基本環境に切り替えています...$(NC)"; \
		$(MAKE) down-mock; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)基本環境を起動しています...$(NC)"
	@IS_MOCK_ENVIRONMENT=0 CRON=0 docker compose up -d --no-deps --force-recreate app && IS_MOCK_ENVIRONMENT=0 CRON=0 docker compose up -d
	@echo "$(GREEN)基本環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443"
	@echo "  phpMyAdmin: http://localhost:8080"

up-cron: ## 基本環境を起動（Cron自動実行モード）
	@if docker network ls | grep -q oc-review-mock_mock-network; then \
		echo "$(YELLOW)Mock環境から基本環境に切り替えています...$(NC)"; \
		$(MAKE) down-mock; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)基本環境を起動しています（Cron自動実行モード）...$(NC)"
	@IS_MOCK_ENVIRONMENT=0 CRON=1 docker compose up -d --no-deps --force-recreate app && IS_MOCK_ENVIRONMENT=0 CRON=1 docker compose up -d
	@echo "$(GREEN)基本環境が起動しました$(NC)"
	@echo "$(YELLOW)Cronモード有効:$(NC) 毎時30分/35分/40分に自動クローリングが実行されます"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "$(YELLOW)Cronログ確認:$(NC)"
	@echo "  docker compose logs -f app"

down: ## 環境を停止（基本環境・Mock環境どちらでも対応）
	@echo "$(RED)環境を停止しています...$(NC)"
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		echo "$(YELLOW)Mock環境を検出しました$(NC)"; \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml down; \
	else \
		docker compose down; \
	fi
	@echo "$(RED)環境が停止しました$(NC)"

restart: down up ## 基本環境を再起動

rebuild: down ## 基本環境を再ビルド
	@echo "$(GREEN)基本環境をビルドしています...$(NC)"
	@docker compose build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up

ssh: ## コンテナにログイン（基本・Mock両対応）
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		docker compose -f docker-compose.yml -f docker-compose.mock.yml exec app bash; \
	else \
		docker compose exec app bash; \
	fi

# Mock付き環境
up-mock: ## Mock付き環境を起動（1万件、遅延なし）
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-mysql-1 && ! docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		echo "$(YELLOW)基本環境からMock環境に切り替えています...$(NC)"; \
		$(MAKE) down; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@./docker/line-mock-api/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています（1万件、遅延なし）...$(NC)"
	@IS_MOCK_ENVIRONMENT=1 CRON=0 MOCK_DELAY_ENABLED=0 MOCK_RANKING_COUNT=10000 MOCK_RISING_COUNT=1000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d --no-deps --force-recreate app line-mock-api && IS_MOCK_ENVIRONMENT=1 CRON=0 MOCK_DELAY_ENABLED=0 MOCK_RANKING_COUNT=10000 MOCK_RISING_COUNT=1000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)データ件数:$(NC) ランキング1万件、急上昇1000件"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443 (基本環境)"
	@echo "  https://localhost:8543 (Mock環境)"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "  LINE Mock API: http://localhost:9000"

up-mock-slow: ## Mock付き環境を起動（10万件、本番並み遅延）
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-mysql-1 && ! docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		echo "$(YELLOW)基本環境からMock環境に切り替えています...$(NC)"; \
		$(MAKE) down; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@./docker/line-mock-api/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています（10万件、本番並み遅延）...$(NC)"
	@IS_MOCK_ENVIRONMENT=1 CRON=0 MOCK_DELAY_ENABLED=1 MOCK_RANKING_COUNT=100000 MOCK_RISING_COUNT=10000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d --no-deps --force-recreate app line-mock-api && IS_MOCK_ENVIRONMENT=1 CRON=0 MOCK_DELAY_ENABLED=1 MOCK_RANKING_COUNT=100000 MOCK_RISING_COUNT=10000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)データ件数:$(NC) ランキング10万件、急上昇1万件"
	@echo "$(YELLOW)遅延モード有効:$(NC) 時間帯により20分～45分の処理時間をシミュレート"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443 (基本環境)"
	@echo "  https://localhost:8543 (Mock環境)"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "  LINE Mock API: http://localhost:9000"

down-mock: down ## Mock付き環境を停止（downと同じ）

restart-mock: down-mock up-mock ## Mock付き環境を再起動

restart-mock-slow: down-mock up-mock-slow ## Mock付き環境を再起動（遅延モード）

up-mock-cron: ## Mock付き環境を起動（1万件、Cron自動実行）
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-mysql-1 && ! docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		echo "$(YELLOW)基本環境からMock環境に切り替えています...$(NC)"; \
		$(MAKE) down; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@./docker/line-mock-api/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています（1万件、Cron自動実行）...$(NC)"
	@IS_MOCK_ENVIRONMENT=1 CRON=1 MOCK_DELAY_ENABLED=0 MOCK_RANKING_COUNT=10000 MOCK_RISING_COUNT=1000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d --no-deps --force-recreate app line-mock-api && IS_MOCK_ENVIRONMENT=1 CRON=1 MOCK_DELAY_ENABLED=0 MOCK_RANKING_COUNT=10000 MOCK_RISING_COUNT=1000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)Cronモード有効:$(NC) 毎時30分/35分/40分に自動クローリングが実行されます"
	@echo "  30分: 日本語（引数なし）"
	@echo "  35分: 繁体字中国語（引数/tw）"
	@echo "  40分: タイ語（引数/th）"
	@echo "$(YELLOW)データ件数:$(NC) ランキング1万件、急上昇1000件"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443 (基本環境)"
	@echo "  https://localhost:8543 (Mock環境)"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "  LINE Mock API: http://localhost:9000"
	@echo "$(YELLOW)Cronログ確認:$(NC)"
	@echo "  docker compose -f docker-compose.yml -f docker-compose.mock.yml logs -f app"

up-mock-slow-cron: ## Mock付き環境を起動（10万件、遅延+Cron）
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-mysql-1 && ! docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
		echo "$(YELLOW)基本環境からMock環境に切り替えています...$(NC)"; \
		$(MAKE) down; \
	fi
	@./docker/app/generate-ssl-certs.sh
	@./docker/line-mock-api/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています（10万件、遅延+Cron）...$(NC)"
	@IS_MOCK_ENVIRONMENT=1 CRON=1 MOCK_DELAY_ENABLED=1 MOCK_RANKING_COUNT=100000 MOCK_RISING_COUNT=10000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d --no-deps --force-recreate app line-mock-api && IS_MOCK_ENVIRONMENT=1 CRON=1 MOCK_DELAY_ENABLED=1 MOCK_RANKING_COUNT=100000 MOCK_RISING_COUNT=10000 docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)Cronモード有効:$(NC) 毎時30分/35分/40分に自動クローリングが実行されます"
	@echo "$(YELLOW)データ件数:$(NC) ランキング10万件、急上昇1万件"
	@echo "$(YELLOW)遅延モード有効:$(NC) 時間帯により20分～45分の処理時間をシミュレート"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443 (基本環境)"
	@echo "  https://localhost:8543 (Mock環境)"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "  LINE Mock API: http://localhost:9000"
	@echo "$(YELLOW)Cronログ確認:$(NC)"
	@echo "  docker compose -f docker-compose.yml -f docker-compose.mock.yml logs -f app"

restart-mock-cron: down-mock up-mock-cron ## Mock付き環境を再起動（Cronモード）

rebuild-mock: down-mock ## Mock付き環境を再ビルド
	@echo "$(GREEN)Mock付き環境をビルドしています...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.mock.yml build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up-mock

ssh-mock: ssh ## Mock環境にログイン（sshと同じ）

show: ## 現在の起動モードを表示
	@echo "$(GREEN)========================================$(NC)"
	@echo "$(GREEN)  現在の起動モード$(NC)"
	@echo "$(GREEN)========================================$(NC)"
	@echo ""
	@if docker ps --format '{{.Names}}' | grep -q oc-review-mock-app-1; then \
		echo "$(YELLOW)起動中のコンテナ:$(NC)"; \
		docker ps --format 'table {{.Names}}\t{{.Status}}' | grep oc-review-mock || echo "なし"; \
		echo ""; \
		if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
			echo "$(YELLOW)環境:$(NC) Mock付き環境"; \
			echo ""; \
			echo "$(YELLOW)Mock API 環境変数:$(NC)"; \
			docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T line-mock-api sh -c 'echo "  MOCK_RANKING_COUNT=$$MOCK_RANKING_COUNT"; echo "  MOCK_RISING_COUNT=$$MOCK_RISING_COUNT"; echo "  MOCK_DELAY_ENABLED=$$MOCK_DELAY_ENABLED"' 2>/dev/null || echo "  (取得失敗)"; \
			echo ""; \
			echo "$(YELLOW)生成されたデータファイル:$(NC)"; \
			docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T line-mock-api sh -c 'ls -lh /app/data/*.json 2>/dev/null | awk "{print \"  \" \$$9 \" (\" \$$5 \")\"}"' || echo "  なし"; \
		else \
			echo "$(YELLOW)環境:$(NC) 基本環境"; \
		fi; \
		echo ""; \
		echo "$(YELLOW)App コンテナ環境変数:$(NC)"; \
		if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
			docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T app sh -c 'echo "  IS_MOCK_ENVIRONMENT=$$IS_MOCK_ENVIRONMENT"; echo "  CRON=$$CRON"' 2>/dev/null || echo "  (取得失敗)"; \
		else \
			docker compose exec -T app sh -c 'echo "  IS_MOCK_ENVIRONMENT=$$IS_MOCK_ENVIRONMENT"; echo "  CRON=$$CRON"' 2>/dev/null || echo "  (取得失敗)"; \
		fi; \
		echo ""; \
		if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
			CRON_STATUS=$$(docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T app sh -c 'if [ -f /etc/cron.d/openchat-crawling ]; then echo "有効"; else echo "無効"; fi' 2>/dev/null); \
		else \
			CRON_STATUS=$$(docker compose exec -T app sh -c 'if [ -f /etc/cron.d/openchat-crawling ]; then echo "有効"; else echo "無効"; fi' 2>/dev/null); \
		fi; \
		echo "$(YELLOW)Cron状態:$(NC) $$CRON_STATUS"; \
		if [ "$$CRON_STATUS" = "有効" ]; then \
			echo "$(YELLOW)スケジュール:$(NC)"; \
			if docker ps --format '{{.Names}}' | grep -q oc-review-mock-line-mock-api-1; then \
				docker compose -f docker-compose.yml -f docker-compose.mock.yml exec -T app sh -c 'cat /etc/cron.d/openchat-crawling 2>/dev/null | grep -v "^#" | grep -v "^$$" | sed "s/^/  /"' 2>/dev/null || echo "  (取得失敗)"; \
			else \
				docker compose exec -T app sh -c 'cat /etc/cron.d/openchat-crawling 2>/dev/null | grep -v "^#" | grep -v "^$$" | sed "s/^/  /"' 2>/dev/null || echo "  (取得失敗)"; \
			fi; \
		fi; \
	else \
		echo "$(RED)コンテナが起動していません$(NC)"; \
		echo ""; \
		echo "$(YELLOW)利用可能なコマンド:$(NC)"; \
		echo "  make up              - 基本環境"; \
		echo "  make up-cron         - 基本環境 (Cron)"; \
		echo "  make up-mock         - Mock環境 (1万件)"; \
		echo "  make up-mock-slow    - Mock環境 (10万件、遅延)"; \
		echo "  make up-mock-cron    - Mock環境 (1万件、Cron)"; \
		echo "  make up-mock-slow-cron - Mock環境 (10万件、遅延+Cron)"; \
	fi
	@echo ""
	@echo "$(GREEN)========================================$(NC)"
