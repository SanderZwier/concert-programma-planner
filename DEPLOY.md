# Deployment Instructies

## Optie 1: Render.com (Gratis - Aanbevolen)

1. Ga naar https://render.com en maak een account aan
2. Klik op "New" → "Web Service"
3. Kies "Build and deploy from a Git repository"
4. Verbind je GitHub account en selecteer deze repository
5. Vul in:
   - Name: `concert-programma-planner`
   - Environment: `Node`
   - Build Command: `npm install`
   - Start Command: `npm start`
6. Klik op "Create Web Service"

Je app is nu online op: `https://concert-programma-planner.onrender.com`

## Optie 2: Railway.app (Gratis tier beschikbaar)

1. Ga naar https://railway.app
2. Klik op "Start a New Project"
3. Kies "Deploy from GitHub repo"
4. Selecteer deze repository
5. Railway detecteert automatisch Node.js en start de app

## Optie 3: Lokaal Testen

```bash
cd /Users/sanderzwier/Desktop/Concert
npm install
npm start
```

Open dan http://localhost:3000 in je browser.

## GitHub Repository Aanmaken

Als je nog geen GitHub repository hebt:

1. Ga naar https://github.com/new
2. Maak een nieuwe repository aan (bijv. `concert-programma-planner`)
3. Voer de volgende commands uit:

```bash
cd /Users/sanderzwier/Desktop/Concert
git remote add origin https://github.com/JOUW_USERNAME/concert-programma-planner.git
git branch -M main
git push -u origin main
```

## Samenwerking Testen

1. Open de app in meerdere browser tabs/vensters
2. Klik op "Samenwerken" en kopieer de sessie link
3. Open de link in een andere browser of deel met iemand anders
4. Wijzigingen synchroniseren automatisch!
