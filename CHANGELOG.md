# Changelog — LicenceFlow

## [1.4.2] — 2026-05-05

### Corrigé
- **`PUT /licenses/{id}` écrasait le `license_type`** — `create_args()` déclarait `license_type` avec `default: 'key'`, donc WP REST injectait toujours `'key'` même quand le champ était absent du payload. Le fallback sur le type existant en DB ne s'activait jamais. Séparation en `update_args()` sans défauts : un champ absent retourne `null` et est traité comme "conserver la valeur existante".
- **`license_type` devient un champ indépendamment modifiable** sur `PUT` — il n'est plus seulement traitable comme effet de bord du champ `license_key`.

## [1.4.1] — 2026-05-04

### Ajouté
- **Code pays WhatsApp configurable** — nouvel option `lflow_stock_alert_whatsapp_country` (défaut `BF`) dans Réglages > Alertes stock. Utilisé par `WTAN_Phone::normalize()` pour normaliser les numéros locaux.
- **Numéros WhatsApp multiples** — le champ accepte plusieurs numéros séparés par des virgules.
- **Alertes stock sur les pages admin** — ajouter ou modifier une licence depuis l'interface admin déclenche maintenant `lflow_stock_after_restore` (réinitialise le flag "déjà notifié" si le stock remonte).
- **Sync stock sur la page d'édition** — `page-edit-license.php` appelle désormais `sync_product_stock()` après une mise à jour réussie (était manquant).

### Corrigé
- **État alertes en tableau unique** — les flags "déjà notifié" étaient stockés dans des options WP individuelles (une par produit). Remplacés par un seul tableau dans l'option `lflow_stock_alert_state` (pas de pollution de wp_options).
- **Invalidation des flags au changement de seuil** — changer le seuil global dans les réglages efface maintenant tous les flags via le hook `update_option_lflow_stock_alert_threshold`.

## [1.4.0] — 2026-05-03

### Ajouté
- **Système d'alertes stock** — email et/ou WhatsApp quand le stock disponible d'un produit passe sous le seuil configuré. Une seule alerte par franchissement de seuil ; le flag est réinitialisé quand le stock remonte.
- `LicenceFlow_Stock_Notifier` — nouveau singleton qui écoute les actions `lflow_stock_after_delivery` et `lflow_stock_after_restore` tirées par le Core.
- Actions custom `lflow_stock_after_delivery` et `lflow_stock_after_restore` dans `class-licenceflow-core.php` — permettent à des composants tiers de réagir aux livraisons et restitutions sans modifier le Core.
- Onglet "Alertes stock" dans Réglages — toggle global, seuil, adresses email, numéro(s) WhatsApp, URL webhook.
- Support WootsApp Notifier (WTAN) : `WTAN_Phone::normalize()`, `WTAN_Api::send()`, `WTAN_Logger::insert()`.
- Webhook fallback pour n8n / Make / Zapier — payload `{"phone":"…","message":"…"}`.
- Filtre `lflow_whatsapp_send` pour override complet par un plugin tiers.
- Cron quotidien `lflow_daily_cron` étendu : scan de toutes les paires produit/variation pour détecter les baisses de stock dues aux expirations.
- Seuil par produit via option WP `lflow_stock_alert_threshold_{product_id}`.

## [1.3.3] — 2026-04-30

### Corrigé
- **Double affichage des variations dans le formulaire "Ajouter une licence"** — deux gestionnaires d'événements distincts (`admin.js::bindAddLicenseForm` et `license-form.js::bindProductChange`) écoutaient tous deux le changement de `#lflow-product-id` et déclenchaient chacun un appel AJAX. Les options de variation étaient donc ajoutées deux fois. Suppression de `bindAddLicenseForm()` dans `admin.js` — `license-form.js` est la seule source de vérité pour ce formulaire.

## [1.3.2] — 2026-04-29

### Corrigé
- **Race condition XHR variations** — si l'utilisateur changeait rapidement de produit, la réponse AJAX d'un ancien produit pouvait s'ajouter après la re-initialisation du select. Ajout d'un abort XHR + re-clear inside callback dans `license-form.js`.

## [1.3.1] — 2026-04-28

### Corrigé
- **Type de licence non détecté automatiquement** — le formulaire d'ajout de licence n'appliquait pas le type configuré sur le produit lors du changement de produit dans le select. Le champ `license_type` reste modifiable mais est désormais pré-rempli depuis la config produit via AJAX.

## [1.3.0] — 2026-04-17

### Ajouté
- **Mode de livraison "Meilleure correspondance" (Best Fit)** — nouvelle stratégie de livraison disponible dans Réglages > Ordre de livraison. Cherche d'abord une clé unique dont la capacité couvre exactement la commande (ou la plus petite capacité suffisante), puis en fallback combine les clés les plus grandes en premier. Minimise le nombre de clés différentes envoyées au client.
- Index SQL sur `remaining_delivre_x_times` ajouté automatiquement à la mise à jour — optimise les requêtes best-fit.

## [1.2.9] — 2026-04-15

### Corrigé
- **Stock sync manquant** — Le stock WooCommerce n'était pas synchronisé dans plusieurs cas : import CSV, sauvegarde du métabox produit, suppression unitaire/en masse via l'admin, et toutes les opérations de l'API REST (création, création en masse, mise à jour, suppression, livraison manuelle). Seuls l'import TXT et l'ajout rapide de licence l'appelaient correctement.
- **Commande annulée/échouée** — Les licences délivrées n'étaient pas restituées quand une commande passait au statut `cancelled` ou `failed`. Seuls les remboursements (`woocommerce_order_refunded`) étaient gérés. Désormais `woocommerce_order_status_changed` déclenche la même logique de restitution.
- **Changement de clé de chiffrement sans migration** — Enregistrer de nouvelles clés dans Réglages > Chiffrement écrasait les options sans re-chiffrer les licences existantes, les rendant illisibles. La sauvegarde déclenche maintenant automatiquement la migration. En cas d'erreur partielle, les clés ne sont pas modifiées.

## [1.2.8] — 2026-04-14

### Ajouté
- Outil de migration des clés de chiffrement (Réglages > Chiffrement) : re-chiffre toutes les licences de l'ancienne clé vers la nouvelle sans perte de données.
- Bouton "Synchroniser tout le stock" dans Réglages > Général.
- Synchronisation du stock lors des changements de statut en masse.

### Corrigé
- `lflow_decrypt()` propageait `false` depuis `openssl_decrypt()` quand la clé/IV ne correspondait pas, affichant les licences comme vides. Désormais, la valeur originale est retournée en cas d'échec de déchiffrement.
- `lflow_maybe_upgrade_db()` appelle `lflow_set_defaults()` pour initialiser les options `lflow_enc_key`/`lflow_enc_iv` lors d'une mise à jour depuis une version antérieure au chiffrement.

## [1.2.7] — 2026-04-13

### Corrigé
- Mise à jour automatique WordPress non détectée : l'updater attendait une GitHub Release publiée sur `/releases/latest`. Les simples commits/push ne suffisent pas.

## [1.2.6] — 2026-04-12

### Ajouté
- Chiffrement AES-256-CBC des clés de licence en base de données.
- Interface de gestion des clés de chiffrement dans Réglages > Chiffrement.

## [1.2.5] — 2026-04-10

### Corrigé
- Licences affichées vides après mise à jour depuis v1.2.0 (régression liée au chiffrement).

## [1.2.0] — 2026-03-15

### Ajouté
- Types de licence : Clé, Compte (user/pass), Lien, Code.
- Import TXT avec note inline (`CLE || note client`).
- Import/Export CSV avancé.
- API REST v1 avec authentification par clé API.
- Synchronisation automatique du stock WooCommerce.
- Support HPOS (High-Performance Order Storage WooCommerce 7.1+).

## [1.0.0] — 2026-02-01

### Ajouté
- Version initiale. Livraison automatique de licences sur commande WooCommerce.
- Tableau de bord admin (liste, ajout, édition, suppression de licences).
- Métabox produit pour configurer la livraison de licences par produit/variation.
- Notifications d'expiration par email (cron quotidien).
- Auto-updater via GitHub Releases.
