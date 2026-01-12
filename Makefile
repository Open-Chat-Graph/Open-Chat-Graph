.PHONY: help init up down restart rebuild ssh up-mock down-mock restart-mock rebuild-mock ssh-mock

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
	@echo "  $(GREEN)make down$(NC)        - 基本環境を停止"
	@echo "  $(GREEN)make restart$(NC)     - 基本環境を再起動"
	@echo "  $(GREEN)make rebuild$(NC)     - 基本環境を再ビルドして起動"
	@echo "  $(GREEN)make ssh$(NC)         - 基本環境のコンテナにログイン"
	@echo ""
	@echo "$(YELLOW)Mock付き環境:$(NC)"
	@echo "  $(GREEN)make up-mock$(NC)     - Mock付き環境を起動"
	@echo "  $(GREEN)make down-mock$(NC)   - Mock付き環境を停止"
	@echo "  $(GREEN)make restart-mock$(NC) - Mock付き環境を再起動"
	@echo "  $(GREEN)make rebuild-mock$(NC) - Mock付き環境を再ビルドして起動"
	@echo "  $(GREEN)make ssh-mock$(NC)    - Mock環境のコンテナにログイン"
	@echo ""
	@echo "$(YELLOW)初回セットアップ:$(NC)"
	@echo "  $(GREEN)make init$(NC)        - SSL証明書生成 + 環境初期化"

init: ## 初回セットアップ
	@echo "$(GREEN)初回セットアップを開始します...$(NC)"
	@./docker/app/generate-ssl-certs.sh
	@if [ ! -f local-secrets.php ]; then \
		echo "$(YELLOW)local-secrets.phpが存在しません。セットアップスクリプトを実行します$(NC)"; \
		if [ -f local-setup.sh ]; then \
			./local-setup.sh; \
		else \
			./setup/local-setup.default.sh; \
		fi \
	fi
	@echo "$(GREEN)初回セットアップが完了しました$(NC)"

# 基本環境
up: ## 基本環境を起動
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)基本環境を起動しています...$(NC)"
	@docker compose up -d
	@echo "$(GREEN)基本環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443"
	@echo "  phpMyAdmin: http://localhost:8080"

down: ## 基本環境を停止
	@echo "$(RED)基本環境を停止しています...$(NC)"
	@docker compose down
	@echo "$(RED)基本環境が停止しました$(NC)"

restart: down up ## 基本環境を再起動

rebuild: down ## 基本環境を再ビルド
	@echo "$(GREEN)基本環境をビルドしています...$(NC)"
	@docker compose build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up

ssh: ## 基本環境にログイン
	@docker compose exec app bash

# Mock付き環境
up-mock: ## Mock付き環境を起動
	@./docker/app/generate-ssl-certs.sh
	@echo "$(GREEN)Mock付き環境を起動しています...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.mock.yml up -d
	@echo "$(GREEN)Mock付き環境が起動しました$(NC)"
	@echo "$(YELLOW)アクセスURL:$(NC)"
	@echo "  https://localhost:8443 (基本環境)"
	@echo "  https://localhost:8543 (Mock環境)"
	@echo "  phpMyAdmin: http://localhost:8080"
	@echo "  LINE Mock API: http://localhost:9000"

down-mock: ## Mock付き環境を停止
	@echo "$(RED)Mock付き環境を停止しています...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.mock.yml down
	@echo "$(RED)Mock付き環境が停止しました$(NC)"

restart-mock: down-mock up-mock ## Mock付き環境を再起動

rebuild-mock: down-mock ## Mock付き環境を再ビルド
	@echo "$(GREEN)Mock付き環境をビルドしています...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.mock.yml build
	@echo "$(GREEN)ビルドが完了しました$(NC)"
	@$(MAKE) up-mock

ssh-mock: ## Mock環境にログイン
	@docker compose -f docker-compose.yml -f docker-compose.mock.yml exec app bash
