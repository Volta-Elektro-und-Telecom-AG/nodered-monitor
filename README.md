Zuerst Linux updaten
```
sudo apt update && sudo apt upgrade
```

Dann Node-Red installieren
```
sudo apt-get install -y git curl build-essential
bash <(curl -sL https://github.com/node-red/linux-installers/releases/latest/download/update-nodejs-and-nodered-deb)
```

Wenn Node-Red erfolgreich installiert:
```
curl -sL https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/nodered-monitor.sh | bash
```
