.PHONY: help init up down restart rebuild ssh up-dev down-dev restart-dev rebuild-dev ssh-dev

# カラー定義
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m

help: ## ヘルプを表示
	@echo "$(GREEN)利用可能なコマンド:$(NC)"
	@echo ""
	@echo "$(YELLOW)開発環境:$(NC)"
	@echo "  $(GREEN)make up-dev$(NC)      - コンテナを起動"
	@echo "  $(GREEN)make down-dev$(NC)    - コンテナを停止"
	@echo "  $(GREEN)make restart-dev$(NC) - コンテナを再起動"
	@echo "  $(GREEN)make rebuild-dev$(NC) - コンテナを再ビルドして起動"
	@echo "  $(GREEN)make ssh-dev$(NC)     - コンテナにログイン"
	@echo ""
	@echo "$(YELLOW)本番環境:$(NC)"
	@echo "  $(GREEN)make up$(NC)          - コンテナを起動"
	@echo "  $(GREEN)make down$(NC)        - コンテナを停止"
	@echo "  $(GREEN)make restart$(NC)     - コンテナを再起動"
	@echo "  $(GREEN)make rebuild$(NC)     - コンテナを再ビルドして起動"
	@echo "  $(GREEN)make ssh$(NC)         - コンテナにログイン"
	@echo ""
	@echo "$(YELLOW)初回セットアップ:$(NC)"
	@echo "  $(GREEN)make init$(NC)        - SSL証明書生成"

init: ## 初回セットアップ
	@echo "$(GREEN)初回セットアップを開始します...$(NC)"
	@./docker/app/generate-ssl-certs.sh
	@if [ ! -f local-secrets.php ]; then \
		echo "$(YELLOW)local-secrets.phpが存在しません。local-setup.shを実行してください$(NC)"; \
		./local-setup.sh; \
	fi
	@echo "$(GREEN)初回セットアップが完了しました$(NC)"

# 開発環境
up-dev: ## 開発環境を起動
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)開発環境を起動しています...$(NC)"
	@docker compose -f docker-compose.dev.yml up -d
	@echo "$(GREEN)開発環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8543"
	@echo "  phpMyAdmin: http://localhost:8180"

down-dev: ## 開発環境を停止
	@echo "$(RED)開発環境を停止しています...$(NC)"
	@docker compose -f docker-compose.dev.yml down
	@echo "$(RED)開発環境が停止しました$(NC)"

restart-dev: down-dev up-dev ## 開発環境を再起動

rebuild-dev: down-dev ## 開発環境を再ビルド
	@echo "$(GREEN)開発環境をビルドしています...$(NC)"
	@docker compose -f docker-compose.dev.yml build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up-dev

ssh-dev: ## 開発環境にログイン
	@docker compose -f docker-compose.dev.yml exec app bash

# 本番環境
up: ## 本番環境を起動
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)本番環境を起動しています...$(NC)"
	@docker compose -f docker-compose.yml up -d
	@echo "$(GREEN)本番環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443"
	@echo "  phpMyAdmin: http://localhost:8080"

down: ## 本番環境を停止
	@echo "$(RED)本番環境を停止しています...$(NC)"
	@docker compose -f docker-compose.yml down
	@echo "$(RED)本番環境が停止しました$(NC)"

restart: down up ## 本番環境を再起動

rebuild: down ## 本番環境を再ビルド
	@echo "$(GREEN)本番環境をビルドしています...$(NC)"
	@docker compose -f docker-compose.yml build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up

ssh: ## 本番環境にログイン
	@docker compose -f docker-compose.yml exec app bash
