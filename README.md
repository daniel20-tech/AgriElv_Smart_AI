# AgriElv Smart AI V2 (PHP 8.1+ / MySQL / Bootstrap 5)

## Installation locale
1. `mysql -u root -p -e "CREATE DATABASE agrielv CHARACTER SET utf8mb4"` puis `mysql -u root -p agrielv < database.sql`
   (base existante en V1 : exécuter une seule fois `migration_v2.sql` à la place).
2. Copier `.env.example` en `.env` et remplir `DB_*`. `APP_DEMO=true` = données capteurs simulées.
3. `php tools/install-libs.php` (une fois, avec internet) : Bootstrap en local dans `assets/vendor/`.
4. `php -S localhost:8000` puis http://localhost:8000

## Compte administrateur
Mettre votre email dans `ADMIN_EMAIL` (.env) puis vous inscrire avec cet email : le compte devient admin et le menu « Admin » apparaît.
Compte déjà créé : `UPDATE users SET role='admin' WHERE email='vous@exemple.com';`

## Fonctions V2
- Connexion/inscription avec robot Armel animé, écran « Bienvenue sur AgriElv », animations (clic, scroll, compteurs, jauges).
- Dashboard : zones A/B/C, vue en direct au centre (simulation, caméra MJPEG/snapshot/vidéo, ou caméra de l'appareil), jauge de santé, contrôles manuels.
- Ajouter caméras et capteurs (clé secrète affichée une seule fois), forum type WhatsApp, rapport PDF généré en JavaScript (`assets/js/pdf.js`).
- Admin : statistiques, utilisateurs (suspendre, promouvoir, supprimer), avis, modération du forum.
- Mascotte Armel : guide par page (visite guidée).

## Caméras
- Sur un site HTTPS, l'URL du flux doit être HTTPS (sinon le navigateur bloque).
- RTSP n'est pas lisible par un navigateur : convertir en MJPEG ou snapshot.
- « Caméra de cet appareil » demande l'autorisation du navigateur (HTTPS requis).

## Connexion Google (optionnel)
Créer un ID client OAuth (Application Web), URI de redirection `APP_URL/google.php`, puis renseigner `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET`.

## Production
`APP_ENV=production`, HTTPS, `.env` hors du dossier public si possible. `prisma/schema.prisma` = documentation du modèle (la BDD réelle = `database.sql`).
