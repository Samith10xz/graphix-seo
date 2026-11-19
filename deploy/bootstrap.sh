#!/usr/bin/env bash
# One-time server setup for a fresh Ubuntu/Debian VPS. Run as root.
# Installs Docker, firewall, swap, a 'deploy' user, and a GitHub deploy key.
set -euo pipefail

. /etc/os-release
case "$ID" in ubuntu|debian) ;; *) echo "Needs Ubuntu or Debian (found $ID)"; exit 1;; esac

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y ca-certificates curl git ufw fail2ban unattended-upgrades openssl

# Docker Engine + compose plugin
if ! command -v docker >/dev/null; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

# 4 GB swap (safety net for builds and big crawls)
if ! swapon --show | grep -q /swapfile; then
  fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# Firewall: SSH, HTTP, HTTPS only
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Deploy user that owns the app and can run docker
id deploy >/dev/null 2>&1 || adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
mkdir -p /opt/graphix-seo && chown deploy:deploy /opt/graphix-seo

# Read-only deploy key for the private GitHub repo
sudo -u deploy mkdir -p /home/deploy/.ssh
if [ ! -f /home/deploy/.ssh/github_deploy ]; then
  sudo -u deploy ssh-keygen -t ed25519 -N "" -C "graphix-seo-vps" -f /home/deploy/.ssh/github_deploy
fi
sudo -u deploy bash -c 'cat > /home/deploy/.ssh/config <<EOF
Host github.com
  IdentityFile ~/.ssh/github_deploy
  StrictHostKeyChecking accept-new
EOF
chmod 600 ~/.ssh/config'

echo
echo "=============================================================="
echo " Server ready. Add this key to GitHub → graphix-seo → Settings"
echo " → Deploy keys → Add deploy key (read-only):"
echo
cat /home/deploy/.ssh/github_deploy.pub
echo "=============================================================="
