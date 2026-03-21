# LicenceFlow — Contexte de développement

> **Pour Claude :** Lis ce fichier en début de session pour comprendre où on en est.

---

## Qui / Quoi

Plugin WordPress WooCommerce nommé **LicenceFlow**, développé pour **Tedisun SARL**.
Il remplace un plugin existant (FS-License-Manager v5.1.7 de Firas Saidi) avec une architecture plus propre et de nouvelles fonctionnalités.

- Plugin de référence (NE PAS MODIFIER) : `../FS-License-Manager/`
- Plugin en développement : `./licenceflow/` ← ici

---

## État : Première version codée, pas encore testée

Tout le code a été écrit. Il reste à :
1. Installer sur un WordPress + WooCommerce de test
2. Corriger les éventuels bugs
3. Tester le flux complet (commande → livraison → email → MCP)

---

## Architecture complète

### Fichiers existants avant cette session (squelette de départ)
| Fichier | Rôle |
|---|---|
| `licenceflow.php` | Main plugin file — headers, constantes, activation hooks, DB setup |
| `includes/functions.php` | Helpers : chiffrement AES-256, types/statuts, dates, render card |

### Fichiers créés/modifiés pendant cette session

**Couche données**
| Fichier | Rôle |
|---|---|
| `includes/class-licenceflow-license-db.php` | Toutes les requêtes DB pour les licences (statique) |
| `includes/class-licenceflow-product-config.php` | Config produits (wp_lflow_licensed_products) |

**Singletons core**
| Fichier | Rôle |
|---|---|
| `includes/class-licenceflow-security.php` | Nonces, sanitisation, vérif API key MCP |
| `includes/class-licenceflow-settings.php` | Lecture/enregistrement des options WP |
| `includes/class-licenceflow-core.php` | Hooks WooCommerce, livraison, cron, admin bar |

**Admin**
| Fichier | Rôle |
|---|---|
| `includes/admin/class-licenceflow-admin.php` | Menus, enqueue assets, handlers AJAX |
| `includes/admin/class-licenceflow-list-table.php` | WP_List_Table pour la liste des licences |
| `includes/admin/page-getting-started.php` | Page d'accueil / mise en route |
| `includes/admin/page-licenses.php` | Liste des licences |
| `includes/admin/page-add-license.php` | Formulaire ajout |
| `includes/admin/page-edit-license.php` | Formulaire édition |
| `includes/admin/page-statistics.php` | Dashboard statistiques |
| `includes/admin/page-import-export.php` | Import/Export CSV |
| `includes/admin/page-settings.php` | Réglages (4 onglets) |

**Metaboxes**
| Fichier | Rôle |
|---|---|
| `includes/metaboxes/class-licenceflow-product-metabox.php` | Metabox produit WC (3 onglets) |
| `includes/metaboxes/class-licenceflow-order-metabox.php` | Metabox commande (licences livrées) |

**API MCP**
| Fichier | Rôle |
|---|---|
| `includes/api/v1/api.php` | 7 endpoints REST `/wp-json/licenceflow/mcp/v1/` |
| `mcp/licenceflow-mcp.json` | Définition des outils pour Claude/agents IA |

**Templates client**
| Fichier | Rôle |
|---|---|
| `templates/email-licenses.php` | Licences dans l'email de commande |
| `templates/thank-you-licenses.php` | Page de confirmation |
| `templates/order-history-licenses.php` | Historique commandes client |

**Assets**
| Fichier | Rôle |
|---|---|
| `assets/css/admin.css` | Styles admin |
| `assets/css/frontend.css` | Styles client |
| `assets/js/admin.js` | AJAX admin (bulk, delete, sync stock, regen API key) |
| `assets/js/license-form.js` | Toggle dynamique des champs par type de licence |

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
  delivre_x_times, remaining_delivre_x_times,
  activation_date, creation_date, sold_date,
  expiration_date (admin seulement — jamais visible client),
  valid (jours de validité client = sold_date + valid),
  order_id,
  admin_notes (notes internes, jamais visibles client)

wp_lflow_licensed_products
  config_id, product_id, variation_id,
  active (TINYINT),
  license_type (template du produit),
  delivery_qty (nb licences par unité),
  show_in (email|website|both)

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
- **`expiration_date`** (colonne DB) = date réelle de la licence → visible admin seulement → alerte X jours avant
- **Expiration client** = `sold_date + valid` jours (calculé à la volée) → jamais l'autre date

### MCP (accès IA)
- Endpoint base : `/wp-json/licenceflow/mcp/v1/`
- Auth : header `X-LicenceFlow-API-Key` (clé dans Réglages > Général)
- 7 outils : `list_licenses`, `get_license`, `create_license`, `update_license`, `delete_license`, `deliver_license`, `get_stats`
- Config Claude Desktop/Code : `mcp/licenceflow-mcp.json`

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
    └── Tab: Statuts de commande
```

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

## Checklist de test (à faire)

- [ ] Activer le plugin sur WordPress + WooCommerce
- [ ] Vérifier que les 3 tables DB sont créées (`wp_lflow_licenses`, etc.)
- [ ] Vérifier l'alerte "clés de chiffrement par défaut"
- [ ] Créer un produit simple, activer LicenceFlow (onglet dans fiche produit)
- [ ] Ajouter 3 licences de type `account` pour ce produit
- [ ] Passer une commande test → vérifier livraison email + thank-you + historique
- [ ] Vérifier le dashboard Statistiques
- [ ] Tester MCP : `GET /wp-json/licenceflow/mcp/v1/stats` avec header API key
- [ ] Tester `POST /wp-json/licenceflow/mcp/v1/licenses` pour créer une licence
- [ ] Forcer le cron `lflow_daily_cron` (via WP Crontrol) → vérifier alertes expiration
- [ ] Tester Import/Export CSV

---

## Commandes utiles pour la suite

```bash
# Vérifier la syntaxe PHP (depuis le dossier licenceflow/)
php -l licenceflow.php
php -l includes/class-licenceflow-core.php

# Générer le .pot complet (si WP-CLI disponible)
wp i18n make-pot . languages/licenceflow.pot --domain=licenceflow
```

---

*Dernière mise à jour : session 2 — plugin entièrement codé, prêt pour les tests.*
