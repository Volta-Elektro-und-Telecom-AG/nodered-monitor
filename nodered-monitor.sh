#!/bin/bash
set -e  # stop on error

# Farben für die Ausgabe
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

headline() {
  echo "\n${CYAN}=============================="
  echo "▶ $1"
  echo "==============================${NC}\n"
}

success() {
  echo "${GREEN}✅ $1${NC}"
}

warn() {
  echo "${YELLOW}⚠️  $1${NC}"
}

error() {
  echo "${RED}❌ $1${NC}"
}


# --- START ---

headline "System Update & Upgrade"
sudo apt-get update && success "Update abgeschlossen"
sudo apt-get upgrade -y && success "Upgrade abgeschlossen"


headline "Installiere Node-RED"
# sudo apt-get install -y git curl build-essential
# bash <(curl -sL https://github.com/node-red/linux-installers/releases/latest/download/update-nodejs-and-nodered-deb) -y
# sleep 10
sudo systemctl enable nodered.service
success "Node-RED im autostart"
sudo systemctl start nodered.service
sleep 10
success "Node-RED gestartet"


headline "Installiere Node-RED Paletten"
cd ~/.node-red
npm install node-red-node-ping \
            node-red-dashboard \
            node-red-contrib-moment
success "Node-RED Paletten installiert"


headline "Importiere Flow"
FLOW_URL="https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/flow.json"
TMP_FILE="/tmp/flows.json"

curl -sL "$FLOW_URL" -o "$TMP_FILE" && success "Flow heruntergeladen"
curl -X POST http://localhost:1880/flows \
     -H "Content-Type: application/json" \
     -d @"$TMP_FILE" && success "Flow in Node-RED importiert"


headline "Installiere Apache2"
sudo apt install -y apache2 && success "Apache2 installiert"


headline "Erstelle Monitoring-Verzeichnis & Dateien"
sudo mkdir -p /var/www/html/monitoring/pings
sudo touch /var/www/html/monitoring/pings/pingfails.json
sudo chmod 777 /var/www/html/monitoring/pings
sudo chmod 777 /var/www/html/monitoring/pings/pingfails.json

curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/pings/index.php" \
  -o /var/www/html/monitoring/pings/index.php
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/pings/data.php" \
  -o /var/www/html/monitoring/pings/data.php
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/volta_logo.webp" \
  -o /var/www/html/monitoring/pings/volta_logo.webp
curl -sL "https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/monitoring/index.php" \
  -o /var/www/html/monitoring/index.php

sudo chmod 777 /dev/vcio

success "Monitoring-Ordner und Dateien erstellt"


headline "Installation erfolgreich abgeschlossen 🎉"
echo "${GREEN}Node-RED läuft unter: http://localhost:1880${NC}"
echo "${GREEN}Monitoring erreichbar unter: http://localhost/monitoring/${NC}"
