#!/bin/bash

set -eo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Log function
log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Check if script is run as root
if [[ $EUID -ne 0 ]]; then
   error "This script must be run as root or with sudo"
fi

# Check if Node.js is already installed
if command -v node &>/dev/null; then
    log "Node.js is already installed"
    exit 0
fi

# Install Node.js via NodeSource
log "Installing Node.js..."
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.4/install.sh | bash
\. "$HOME/.nvm/nvm.sh"
nvm install 22

# Check if Node.js and NPM were installed successfully
if ! command -v node &>/dev/null || ! command -v npm &>/dev/null; then
    error "Node.js or NPM installation failed"
fi

# Installing PNPM globally
log "Installing PNPM globally..."
npm install -g pnpm

# Check if PNPM was installed successfully
if ! command -v pnpm &>/dev/null; then
    error "PNPM installation failed"
fi

# Add information about removal
log "To uninstall Node in the future, run:"
echo "  sudo nvm uninstall 22"
echo "  sudo rm -rf ~/.nvm"

log "Installation completed successfully!"
