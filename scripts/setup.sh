#!/usr/bin/env bash
# One-command setup for new contributors
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "🚀 Setting up JobAzmoon..."

# Check requirements
command -v php >/dev/null || { echo "❌ PHP not found"; exit 1; }
command -v composer >/dev/null || { echo "❌ Composer not found"; exit 1; }
command -v npm >/dev/null || { echo "❌ npm not found"; exit 1; }

php -v | head -1
composer --version | head -1
npm --version

echo "📦 Installing PHP dependencies..."
composer install

echo "📦 Installing Node dependencies..."
npm install

echo "⚙️  Setting up environment..."
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
  echo "✅ .env created. Please edit it with your DB credentials."
else
  echo "⚠️  .env already exists, skipping."
fi

echo "🗄️  Running migrations..."
php artisan migrate --seed --force

echo "🔗 Creating storage link..."
php artisan storage:link || true

echo "🏗️  Building frontend..."
npm run build

echo ""
echo "✅ Setup complete! Run 'composer run dev' to start development."
