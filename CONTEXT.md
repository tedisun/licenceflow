# LicenceFlow — Contexte de développement

> **Pour l'Assistant IA :** Lis ce fichier en début de session pour comprendre où on en est.
> 
> 🔴 **RÈGLE ABSOLUE DE VERSIONING :** Chaque fois qu'une nouvelle fonctionnalité, modification majeure ou correction de bug est implémentée et poussée avec succès, vous **devez impérativement** :
> 1. Incrémenter le numéro de version dans `licenceflow.php` (à la fois dans l'en-tête du fichier `Version: X.Y.Z` et dans la constante `define( 'LFLOW_VERSION', 'X.Y.Z' )`).
> 2. Documenter en détail ce changement en créant une entrée datée sous forme de version dans `CHANGELOG.md`.
> 3. Mettre à jour la section "État actuel" et la ligne de "Dernière mise à jour" à la fin de ce fichier `CONTEXT.md`.
> Ce processus est obligatoire pour bust le cache d'actifs CSS/JS des navigateurs de l'administrateur.

---

## Qui / Quoi

Plugin WordPress WooCommerce nommé **LicenceFlow**, développé pour **Tedisun SARL**.
Il remplace un plugin existant (FS-License-Manager v5.1.7 de Firas Saidi) avec une architecture plus propre et de nouvelles fonctionnalités.

- Plugin de référence (NE PAS MODIFIER) : `../FS-License-Manager/`
- Plugin en développement : `./licenceflow/` ← ici

---

## État actuel : v1.5.0 — en production / Analyse Sélective & Filtres Actifs

Le plugin est opérationnel. Les développements récents couvrent :
- **Audit & Vérification sélective (v1.5.0)** : Liste blanche de produits via un panneau central dynamique de recherche en direct, enforcement SQL à 18h et contrôles d'accès AJAX/UI de sécurité.
- **Résilience pannes réseau (v1.5.0)** : Coupures et timeouts de l'API signalés proprement au lieu de désactiver faussement les clés.
- Livraison automatique WooCommerce + modes FIFO, LIFO, Best Fit
- Système d'alertes stock (email + WhatsApp via WootsApp Notifier)
- API REST avec fix du `PUT /licenses/{id}` (license_type préservé si absent)
- Gestion complète remboursement / annulation / échec

---

## Architecture complète

### Fichiers principaux
| Fichier | Rôle |
|---|---|
| `licenceflow.php` | Main plugin file — headers, constantes, activation/désactivation, DB setup, defaults |
| `includes/functions.php` | Helpers : chiffrement AES-256, types/statuts, dates, render card |

### Couche données
| Fichier | Rôle |
|---|---|
| `includes/class-licenceflow-license-db.php` | Toutes les requêtes DB pour les licences (statique) |
| `includes/class-licenceflow-product-config.php` | Config produits (`wp_lflow_licensed_products`) |

### Singletons core
| Fichier | Rôle |
|---|---|
| `includes/class-licenceflow-security.php` | Nonces, sanitisation, vérif clé API |
| `includes/class-licenceflow-settings.php` | Lecture/enregistrement des options WP + migrations chiffrement |
| `includes/class-licenceflow-core.php` | Hooks WooCommerce, livraison, restitution, cron, admin bar |
| `includes/class-licenceflow-stock-notifier.php` | Alertes stock bas — email + WhatsApp |
| `includes/class-licenceflow-updater.php` | Auto-update via GitHub Releases |

### Admin
| Fichier | Rôle |
|---|---|
| `includes/admin/class-licenceflow-admin.php` | Menus, enqueue assets, handlers AJAX |
| `includes/admin/class-licenceflow-list-table.php` | WP_List_Table pour la liste des licences |
| `includes/admin/page-getting-started.php` | Page d'accueil / mise en route |
| `includes/admin/page-licenses.php` | Liste des licences |
| `includes/admin/page-add-license.php` | Formulaire ajout (déclenche `lflow_stock_after_restore` après insert) |
| `includes/admin/page-edit-license.php` | Formulaire édition (sync stock + `lflow_stock_after_restore` après update) |
| `includes/admin/page-statistics.php` | Dashboard statistiques |
| `includes/admin/page-import-export.php` | Import/Export CSV |
| `includes/admin/page-settings.php` | Réglages (5 onglets — voir menus) |

### Metaboxes
| Fichier | Rôle |
|---|---|
| `includes/metaboxes/class-licenceflow-product-metabox.php` | Metabox produit WC (config par produit/variation) |
| `includes/metaboxes/class-licenceflow-order-metabox.php` | Metabox commande (licences livrées) |

### API REST
| Fichier | Rôle |
|---|---|
| `includes/api/v1/api.php` | 7 endpoints REST `/wp-json/licenceflow/mcp/v1/` |
| `mcp/licenceflow-mcp.json` | Définition des outils pour Claude/agents IA |

### Templates client
| Fichier | Rôle |
|---|---|
| `templates/email-licenses.php` | Licences dans l'email de commande |
| `templates/thank-you-licenses.php` | Page de confirmation |
| `templates/order-history-licenses.php` | Historique commandes client |
| `templates/pdf-licenses.php` | PDF via WooCommerce PDF Invoices & Packing Slips |

### Assets
| Fichier | Rôle |
|---|---|
| `assets/css/admin.css` | Styles admin |
| `assets/css/frontend.css` | Styles client |
| `assets/js/admin.js` | AJAX admin (bulk, delete, sync stock, regen API key) |
| `assets/js/license-form.js` | Formulaire ajout : toggle champs dynamiques + AJAX variations (avec abort XHR) |

---

## Base de données

### Tables créées à l'activation
```sql
wp_lflow_licenses
  license_id, product_id, variation_id,
  license_key (TEXT, chiffré AES-256),
  license_type (key|account|link|code),
  license_status (available|sold|active|inactive|expired|returned|redeemed),
  owner_first_name, owner_last_name, owner_email_address,
  delivre_x_times,            -- capacité maximale de livraisons
  remaining_delivre_x_times,  -- livraisons encore possibles (décrémenté à chaque vente)
  activation_date, creation_date, sold_date,
  expiration_date (admin seulement — jamais visible client),
  valid (jours de validité client = sold_date + valid),
  order_id,
  admin_notes (notes internes, jamais visibles client),
  license_note (note visible client — email, thank-you, historique, PDF)

wp_lflow_licensed_products
  config_id, product_id, variation_id,
  active (TINYINT),
  license_type (template du produit),
  delivery_qty (nb licences par unité commandée),
  show_in (email|website|both),
  default_valid (validité par défaut en jours)

wp_lflow_license_meta
  meta_id, license_id, meta_key, meta_value
```

---

## Concepts clés

### 4 types de licences
| Type | Stockage (JSON chiffré) | Affichage client |
|---|---|---|
| `key` | string brut | `<code>` + bouton Copier |
| `account` | `{"username":"...","password":"..."}` | Tableau + toggle show/hide password |
| `link` | `{"url":"...","label":"..."}` | Bouton lien stylisé |
| `code` | `{"code":"...","note":"..."}` | `<code>` + note |

### Double date d'expiration
- **`expiration_date`** (colonne DB) = date réelle de la licence → visible admin seulement → alerte cron X jours avant
- **Expiration client** = `sold_date + valid` jours (calculé à la volée) → jamais l'autre date

### Compteur de livraisons (`delivre_x_times`)
Une licence peut être livrée à plusieurs commandes différentes :
- `delivre_x_times` = capacité totale (ex. 3 = peut être livrée 3 fois en tout)
- `remaining_delivre_x_times` = livraisons restantes, décrémenté à chaque livraison
- `license_status = 'sold'` seulement quand `remaining_delivre_x_times = 0`
- Cas d'usage typique : compte Netflix partageable, code promo multi-usage

### Modes de livraison (`lflow_key_delivery`)
| Mode | Comportement |
|---|---|
| `fifo` | (défaut) Première licence ajoutée = première livrée |
| `lifo` | Dernière licence ajoutée = première livrée |
| `best_fit` | Cherche la clé dont la capacité couvre exactement la commande, puis la plus petite suffisante. Minimise le nombre de clés différentes livrées. |

### Restitution de licences (remboursement / annulation / échec)
Les trois événements déclenchent la **même logique** via `LicenceFlow_Core::handle_refund()` :

| Événement WooCommerce | Hook | Déclencheur |
|---|---|---|
| Remboursement | `woocommerce_order_refunded` | Directement `handle_refund()` |
| Annulation | `woocommerce_order_status_changed` → `cancelled` | Via `handle_order_status_changed()` → `handle_refund()` |
| Échec | `woocommerce_order_status_changed` → `failed` | Via `handle_order_status_changed()` → `handle_refund()` |

**Ce que fait `handle_refund()` pour chaque licence liée à la commande :**
1. `remaining_delivre_x_times` += N (nombre de fois livrée pour cette commande, plafonné à `delivre_x_times`)
2. `license_status` → `'available'`
3. Si **complètement restituée** (`new_remaining === delivre_x_times`) : `owner_email_address`, `owner_first_name`, `owner_last_name`, `order_id`, `sold_date`, `activation_date` sont effacés
4. Si **partiellement restituée** (`delivre_x_times > 1`) : infos client conservées, statut `available` quand même
5. `sync_product_stock()` + action `lflow_stock_after_restore` tirée

**Point important :** le flag `_lflow_delivered = 1` sur la commande n'est **pas** effacé lors d'une restitution. Si la commande est ré-ouverte et repassée en `completed`, les licences ne seront **pas** re-livrées (guard anti-doublon intentionnel).

### Système d'alertes stock (`LicenceFlow_Stock_Notifier`)
Singleton qui envoie email + WhatsApp quand le stock disponible d'un produit passe sous le seuil.

**Architecture :**
- Déclenché par actions custom (pas par polling) :
  - `lflow_stock_after_delivery` → `maybe_notify()` — vérifie si alerte à envoyer
  - `lflow_stock_after_restore` → `maybe_reset()` — efface le flag "déjà notifié" si stock > seuil
  - `lflow_daily_cron` → `cron_check()` — scan quotidien pour les baisses dues aux expirations
- État stocké dans l'option `lflow_stock_alert_state` (tableau unique, pas une option par produit)
- Une seule alerte par "crossing" (flag effacé quand stock remonte au-dessus du seuil)
- Changer le seuil global (`update_option_lflow_stock_alert_threshold`) efface tous les flags

**Canaux de notification :**
1. Email — liste d'adresses comma-separated (`lflow_stock_alert_emails`), fallback sur l'email admin
2. WhatsApp — plusieurs numéros comma-separated (`lflow_stock_alert_whatsapp`)
   - Priorité 1 : filtre `lflow_whatsapp_send` (override tiers)
   - Priorité 2 : `WTAN_Api::send()` + `WTAN_Phone::normalize()` + `WTAN_Logger::insert()` (WootsApp Notifier)
   - Priorité 3 : webhook externe (`lflow_stock_alert_webhook_url`) → payload `{"phone":"…","message":"…"}`
- Code pays WhatsApp : `lflow_stock_alert_whatsapp_country` (défaut `BF`) — utilisé par `WTAN_Phone::normalize()`
- Seuil par produit : option WP `lflow_stock_alert_threshold_{product_id}` (surcharge le seuil global)

### Actions custom (hooks extensibles)
| Action | Arguments | Tiré par |
|---|---|---|
| `lflow_stock_after_delivery` | `$product_id, $variation_id` | `Core::deliver_licenses_for_order()` après chaque produit livré |
| `lflow_stock_after_restore` | `$product_id, $variation_id` | `Core::handle_refund()`, `page-add-license.php`, `page-edit-license.php` |
| `lflow_licenses_delivered` | `$order_id, $all_ids[]` | `Core::deliver_licenses_for_order()` à la fin de la livraison complète |
| `lflow_daily_cron` | — | Cron WP quotidien (planifié à l'activation) |

### API REST
- Base : `/wp-json/licenceflow/mcp/v1/`
- Auth : header `X-LicenceFlow-API-Key`
- 7 endpoints :

| Méthode | Endpoint | Description |
|---|---|---|
| `GET` | `/licenses` | Liste paginée avec filtres |
| `POST` | `/licenses` | Créer une licence |
| `POST` | `/licenses/bulk` | Créer jusqu'à 200 licences (max) |
| `GET` | `/licenses/{id}` | Détail d'une licence |
| `PUT` | `/licenses/{id}` | Modifier une licence (partiel — champs absents = préservés) |
| `DELETE` | `/licenses/{id}` | Supprimer |
| `POST` | `/licenses/{id}/deliver` | Livrer manuellement à une commande |
| `GET` | `/stats` | Dashboard statistiques |

**`PUT /licenses/{id}` — comportement du `license_type` :**
- Champ absent du payload → type DB préservé
- Champ présent seul (sans `license_key`) → type mis à jour
- Les deux présents → re-sérialisation de la clé avec le nouveau type

---

## Menus admin
```
LicenceFlow (dashicons-lock)
├── Démarrage           page: licenceflow
├── Licences            page: lflow-licenses  (+ ?action=edit&license_id=N)
├── Statistiques        page: lflow-statistics
├── Import / Export     page: lflow-import-export
└── Réglages            page: lflow-settings
    ├── Tab: Général
    ├── Tab: Chiffrement
    ├── Tab: Notifications
    ├── Tab: Statuts de commande
    └── Tab: Alertes stock
```

---

## Options WP importantes

| Option | Défaut | Description |
|---|---|---|
| `lflow_key_delivery` | `fifo` | Mode de livraison : `fifo`, `lifo`, `best_fit` |
| `lflow_stock_sync` | `''` | Synchroniser stock WooCommerce avec licences disponibles |
| `lflow_stock_alert_enabled` | `''` | Activer les alertes stock bas |
| `lflow_stock_alert_threshold` | `2` | Seuil global (nb d'unités) |
| `lflow_stock_alert_emails` | `''` | Adresses email comma-separated |
| `lflow_stock_alert_whatsapp` | `+22654819666` | Numéros WhatsApp comma-separated |
| `lflow_stock_alert_whatsapp_country` | `BF` | Code pays ISO pour normalisation WTAN |
| `lflow_stock_alert_webhook_url` | `''` | URL webhook fallback WhatsApp |
| `lflow_stock_alert_state` | `[]` | État interne — flags "déjà notifié" par produit |
| `lflow_stock_alert_threshold_{product_id}` | — | Seuil par produit (override global) |

---

## Features supprimées (vs FS-License-Manager original)
- ~~Générateur de licences~~ (prefix-chunks-suffix)
- ~~Activation par device~~ (device_id, max instances, activate/deactivate)
- ~~QR codes~~
- ~~API v1 et v2~~ (legacy avec clés hardcodées)
- ~~Action Scheduler / Queue~~
- ~~Page Extensions~~
- ~~Page Welcome~~ → remplacée par "Démarrage" utile

---

## Commandes utiles

```bash
# Vérifier la syntaxe PHP
php -l licenceflow.php
php -l includes/class-licenceflow-core.php

# Générer le .pot (si WP-CLI disponible)
wp i18n make-pot . languages/licenceflow.pot --domain=licenceflow
```

---

*Dernière mise à jour : v1.5.0*
