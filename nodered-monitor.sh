#!/bin/sh
set -e

# Farben
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

headline() {
  printf "\n%s==============================\n" "$CYAN"
  printf "▶ %s\n" "$1"
  printf "==============================%s\n\n" "$NC"
}

success() {
  printf "%s✅ %s%s\n" "$GREEN" "$1" "$NC"
}

warn() {
  printf "%s⚠️  %s%s\n" "$YELLOW" "$1" "$NC"
}

error() {
  printf "%s❌ %s%s\n" "$RED" "$1" "$NC"
}

# --- START ---

headline "System Update & Upgrade"
sudo apt-get update && success "Update abgeschlossen"
sudo apt-get upgrade -y && success "Upgrade abgeschlossen"

headline "Installiere Node-RED"
sudo apt-get install -y git curl build-essential
bash <(curl -sL https://github.com/node-red/linux-installers/releases/latest/download/update-nodejs-and-nodered-deb) --yes
sleep 10
sudo systemctl enable nodered.service
sudo systemctl start nodered.service
sleep 5
success "Node-RED gestartet"

headline "Installiere Node-RED Paletten"
cd "$HOME/.node-red" || exit 1
npm install node-red-node-ping node-red-dashboard node-red-contrib-moment
success "Node-RED Paletten installiert"

headline "Importiere Flow"
FLOW_URL="https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/flow.json"
TMP_FILE="/tmp/flows.json"

curl -sL "$FLOW_URL" -o "$TMP_FILE" && success "Flow heruntergeladen"
curl -X POST http://localhost:1880/flows -H "Content-Type: application/json" -d @"$TMP_FILE" && success "Flow in Node-RED importiert"
sleep 5

headline "Installiere Apache2"
sudo apt install -y apache2 php libapache2-mod-php php-cli && success "Apache2 + PHP installiert"
sleep 5

headline "Erstelle Monitoring-Verzeichnis & Dateien"
sudo mkdir -p /var/www/html/monitoring/pings
sudo touch /var/www/html/monitoring/pings/pingfails.json
sudo chmod 777 /var/www/html/monitoring/pings
sudo chmod 777 /var/www/html/monitoring/pings/pingfails.json

curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/pings/index.php" -o /var/www/html/monitoring/pings/index.php
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/pings/data.php" -o /var/www/html/monitoring/pings/data.php
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/volta_logo.webp" -o /var/www/html/monitoring/pings/volta_logo.webp
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/index.php" -o /var/www/html/monitoring/index.php

sudo chmod 777 /dev/vcio

success "Monitoring-Ordner und Dateien erstellt"
sleep 5

headline "Installation erfolgreich abgeschlossen 🎉"
printf "%sNode-RED läuft unter: http://localhost:1880%s\n" "$GREEN" "$NC"
printf "%sMonitoring erreichbar unter: http://localhost/monitoring/%s\n" "$GREEN" "$NC"
