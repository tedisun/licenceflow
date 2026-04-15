# Changelog — LicenceFlow

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
