# Codespaces用の開発環境コンテナ
# Docker in Dockerでコンテナを起動できるUbuntu環境
FROM ubuntu:24.04

# 基本的なツールのインストール
RUN apt-get update && \
    apt-get install -y \
    ca-certificates \
    curl \
    git \
    make \
    wget \
    sudo \
    vim \
    && rm -rf /var/lib/apt/lists/*

# mkcertのインストール
RUN wget -q https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64 && \
    chmod +x mkcert-v1.4.4-linux-amd64 && \
    mv mkcert-v1.4.4-linux-amd64 /usr/local/bin/mkcert

# ubuntuユーザーにsudo権限を付与（パスワード不要）
RUN echo "ubuntu ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/ubuntu && \
    chmod 0440 /etc/sudoers.d/ubuntu

WORKDIR /workspaces
