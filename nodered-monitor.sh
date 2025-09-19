#!/bin/bash

# Farben mit tput (Fallback auf leer)
GREEN=$(tput setaf 2 || true)
CYAN=$(tput setaf 6 || true)
YELLOW=$(tput setaf 3 || true)
RED=$(tput setaf 1 || true)
BOLD=$(tput bold || true)
NC=$(tput sgr0 || true)

FAILED_CMDS=()

headline() {
  printf "\n%s==============================\n" "${CYAN}${BOLD}"
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

# Wrapper um Commands auszuführen
run_cmd() {
  local cmd="$1"
  local msg="$2"

  printf "%s\n" "${BOLD}→ $cmd${NC}"
  if eval "$cmd"; then
    success "$msg"
  else
    error "$msg fehlgeschlagen"
    FAILED_CMDS+=("$msg ($cmd)")
  fi
}

# --- START ---

headline "System Update & Upgrade"
run_cmd "sudo apt-get update -y" "Update abgeschlossen"
run_cmd "sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -yq" "Upgrade abgeschlossen"

headline "Installiere Node-RED"
#run_cmd "sudo apt-get install -y git curl build-essential" "Build-Tools installiert"
#run_cmd "bash <(curl -sL https://github.com/node-red/linux-installers/releases/latest/download/update-nodejs-and-nodered-deb) --yes" "Node.js & Node-RED installiert"
run_cmd "sudo systemctl enable nodered.service" "Node-RED Autostart aktiviert"
run_cmd "sudo systemctl start nodered.service" "Node-RED gestartet"
sleep 5

headline "Installiere Node-RED Paletten"
cd "$HOME/.node-red" || FAILED_CMDS+=("Cannot cd to $HOME/.node-red")

run_cmd "npm install node-red-node-ping" "node-red-node-ping installiert"
run_cmd "npm install node-red-dashboard" "node-red-dashboard installiert"
run_cmd "npm install node-red-contrib-moment" "node-red-contrib-moment installiert"


headline "Importiere Flow"
FLOW_URL="https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/flows.json"
TMP_FILE="/tmp/flows.json"
run_cmd "curl -sL \"$FLOW_URL\" -o \"$TMP_FILE\"" "Flow heruntergeladen"
run_cmd "curl -X POST http://localhost:1880/flows -H \"Content-Type: application/json\" -d @\"$TMP_FILE\"" "Flow in Node-RED importiert"
sleep 5

headline "Installiere Apache2"
run_cmd "sudo apt install -y apache2 php libapache2-mod-php php-cli" "Apache2 + PHP installiert"
sleep 5

headline "Erstelle Monitoring-Verzeichnis & Dateien"
run_cmd "sudo mkdir -p /var/www/html/monitoring/pings" "Monitoring-Verzeichnis erstellt"
run_cmd "sudo touch /var/www/html/monitoring/pings/pingfails.json" "pingfails.json erstellt"
run_cmd "sudo chmod 777 /var/www/html/monitoring/pings" "Rechte für Monitoring-Verzeichnis gesetzt"
run_cmd "sudo chmod 777 /var/www/html/monitoring/pings/pingfails.json" "Rechte für pingfails.json gesetzt"

run_cmd "sudo curl -sL \"https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/monitoring/pings/index.php\" -o /var/www/html/monitoring/pings/index.php" "index.php heruntergeladen"
run_cmd "sudo curl -sL \"https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/monitoring/pings/data.php\" -o /var/www/html/monitoring/pings/data.php" "data.php heruntergeladen"
run_cmd "sudo curl -sL \"https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/volta_logo.webp\" -o /var/www/html/monitoring/pings/volta_logo.webp" "volta_logo.webp heruntergeladen"
run_cmd "sudo curl -sL \"https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/monitoring/index.php\" -o /var/www/html/monitoring/index.php" "monitoring/index.php heruntergeladen"

run_cmd "sudo rm -f /var/www/html/index.html" "Standard-index.html entfernt"
run_cmd "sudo curl -sL \"https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/main/index.php\" -o /var/www/html/index.php" "Root index.php heruntergeladen"

run_cmd "sudo chmod 777 /dev/vcio" "Rechte für /dev/vcio gesetzt"

headline "Installation abgeschlossen 🎉"
printf "%sNode-RED läuft unter: http://localhost:1880%s\n" "$GREEN" "$NC"
printf "%sMonitoring erreichbar unter: http://localhost/monitoring/%s\n" "$GREEN" "$NC"

# --- Zusammenfassung ---
if [ ${#FAILED_CMDS[@]} -gt 0 ]; then
  printf "\n%s==============================\n" "${RED}${BOLD}"
  printf "❌ Folgende Schritte konnten nicht erfolgreich abgeschlossen werden:\n"
  printf "==============================%s\n" "$NC"
  for f in "${FAILED_CMDS[@]}"; do
    printf "%s- %s%s\n" "$RED" "$f" "$NC"
  done
  printf "\n%sBitte manuell prüfen und beheben.%s\n" "$YELLOW" "$NC"
else
  printf "\n%sAlle Schritte wurden erfolgreich abgeschlossen 🎉%s\n" "$GREEN" "$NC"
fi
