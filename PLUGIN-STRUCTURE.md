# Smart Cycle Discounts - Plugin Structure Analysis

> **Purpose:** Complete file listing organized by type and folder, sorted by LOC

---

## Table of Contents

1. [PHP Files](#php-files)
2. [JavaScript Files](#javascript-files)
3. [CSS/SCSS Files](#cssscss-files)
4. [Summary Statistics](#summary-statistics)

---


## PHP Files

### 📁 `./`

| File Name | LOC |
|-----------|----:|
| `smart-cycle-discounts.php` | 655 |
| `uninstall.php` | 473 |

**Folder Total: 2 files, 1128 LOC**

### 📁 `includes/`

| File Name | LOC |
|-----------|----:|
| `class-smart-cycle-discounts.php` | 1071 |
| `class-activator.php` | 561 |
| `class-deactivator.php` | 531 |
| `class-recurring-handler.php` | 497 |
| `class-autoloader.php` | 417 |
| `class-loader.php` | 410 |
| `class-i18n.php` | 390 |

**Folder Total: 7 files, 3877 LOC**

### 📁 `includes/admin/`

| File Name | LOC |
|-----------|----:|
| `class-menu-manager.php` | 774 |
| `class-admin-manager.php` | 729 |
| `class-capability-manager.php` | 667 |
| `class-admin.php` | 406 |
| `class-admin-asset-manager.php` | 301 |
| `class-currency-change-notices.php` | 201 |
| `class-campaign-expiration-notices.php` | 195 |

**Folder Total: 7 files, 3273 LOC**

### 📁 `includes/admin/ajax/`

| File Name | LOC |
|-----------|----:|
| `class-ajax-router.php` | 915 |
| `class-ajax-security.php` | 753 |
| `abstract-class-ajax-handler.php` | 540 |
| `class-scd-ajax-response.php` | 406 |
| `trait-wizard-helpers.php` | 255 |
| `trait-license-validation.php` | 204 |

**Folder Total: 6 files, 3073 LOC**

### 📁 `includes/admin/ajax/handlers/`

| File Name | LOC |
|-----------|----:|
| `class-campaign-validation-handler.php` | 983 |
| `class-draft-handler.php` | 778 |
| `class-save-step-handler.php` | 559 |
| `class-product-search-handler.php` | 480 |
| `class-discount-api-handler.php` | 383 |
| `class-send-test-email-handler.php` | 355 |
| `class-apply-recommendation-handler.php` | 340 |
| `class-preview-coverage-handler.php` | 328 |
| `class-get-active-campaigns-handler.php` | 303 |
| `class-import-export-handler.php` | 266 |
| `class-log-viewer-handler.php` | 265 |
| `class-profit-margin-warning-handler.php` | 240 |
| `class-tools-handler.php` | 223 |
| `class-import-handler.php` | 219 |
| `class-check-conflicts-handler.php` | 216 |
| `class-debug-log-handler.php` | 210 |
| `class-ajax-debug-log.php` | 202 |
| `class-test-provider-connection-handler.php` | 201 |
| `class-get-summary-handler.php` | 199 |
| `class-calculate-discount-impact-handler.php` | 192 |
| `class-get-product-stats-handler.php` | 192 |
| `class-recover-session-handler.php` | 185 |
| `class-load-data-handler.php` | 183 |
| `class-quick-edit-handler.php` | 182 |
| `class-health-check-handler.php` | 175 |
| `class-check-campaign-name-handler.php` | 164 |
| `class-main-dashboard-data-handler.php` | 159 |
| `class-clear-cache-handler.php` | 150 |
| `class-clear-license-cache-handler.php` | 148 |
| `class-sale-items-filter-handler.php` | 136 |
| `class-campaign-health-handler.php` | 131 |
| `class-retry-failed-emails-handler.php` | 123 |
| `class-campaign-performance-handler.php` | 122 |
| `class-process-queue-handler.php` | 121 |
| `class-clear-queue-handler.php` | 118 |
| `class-export-handler.php` | 113 |
| `class-revenue-trend-handler.php` | 113 |
| `class-session-status-handler.php` | 111 |
| `class-activity-feed-handler.php` | 108 |
| `class-top-products-handler.php` | 107 |
| `class-track-event-handler.php` | 107 |
| `class-console-logger-handler.php` | 91 |
| `class-check-session-handler.php` | 90 |
| `class-overview-handler.php` | 89 |

**Folder Total: 45 files, 10347 LOC**

### 📁 `includes/admin/assets/`

| File Name | LOC |
|-----------|----:|
| `class-asset-localizer.php` | 1336 |
| `class-script-registry.php` | 1115 |
| `class-style-registry.php` | 698 |
| `class-asset-loader.php` | 558 |
| `class-theme-color-inline-styles.php` | 353 |

**Folder Total: 5 files, 4060 LOC**

### 📁 `includes/admin/components/`

| File Name | LOC |
|-----------|----:|
| `class-campaigns-list-table.php` | 1631 |
| `class-condition-builder.php` | 766 |
| `class-modal-component.php` | 548 |
| `class-chart-renderer.php` | 538 |

**Folder Total: 4 files, 3483 LOC**

### 📁 `includes/admin/helpers/`

| File Name | LOC |
|-----------|----:|
| `class-tooltip-helper.php` | 121 |

**Folder Total: 1 files, 121 LOC**

### 📁 `includes/admin/licensing/`

| File Name | LOC |
|-----------|----:|
| `class-upgrade-prompt-manager.php` | 478 |
| `class-license-manager.php` | 454 |
| `class-freemius-integration.php` | 443 |
| `class-feature-gate.php` | 439 |
| `class-license-notices.php` | 143 |
| `license-functions.php` | 134 |

**Folder Total: 6 files, 2091 LOC**

### 📁 `includes/admin/pages/`

| File Name | LOC |
|-----------|----:|
| `class-tools-page.php` | 549 |
| `class-analytics-dashboard.php` | 530 |
| `class-campaign-cron-diagnostic.php` | 435 |
| `class-analytics-page.php` | 375 |
| `class-currency-review-page.php` | 287 |
| `class-license-emergency-fix.php` | 249 |
| `class-campaigns-page.php` | 243 |

**Folder Total: 7 files, 2668 LOC**

### 📁 `includes/admin/pages/dashboard/`

| File Name | LOC |
|-----------|----:|
| `class-main-dashboard-page.php` | 1714 |

**Folder Total: 1 files, 1714 LOC**

### 📁 `includes/admin/pages/notifications/`

| File Name | LOC |
|-----------|----:|
| `class-notifications-tab-base.php` | 423 |
| `class-notifications-page.php` | 394 |

**Folder Total: 2 files, 817 LOC**

### 📁 `includes/admin/pages/notifications/tabs/`

| File Name | LOC |
|-----------|----:|
| `class-notifications-settings-tab.php` | 701 |
| `class-queue-status-tab.php` | 282 |

**Folder Total: 2 files, 983 LOC**

### 📁 `includes/admin/settings/`

| File Name | LOC |
|-----------|----:|
| `class-settings-manager.php` | 457 |
| `class-settings-page-base.php` | 440 |

**Folder Total: 2 files, 897 LOC**

### 📁 `includes/admin/settings/tabs/`

| File Name | LOC |
|-----------|----:|
| `class-performance-settings.php` | 380 |
| `class-advanced-settings.php` | 236 |
| `class-general-settings.php` | 200 |

**Folder Total: 3 files, 816 LOC**

### 📁 `includes/bootstrap/`

| File Name | LOC |
|-----------|----:|
| `class-service-definitions.php` | 978 |
| `class-container.php` | 730 |
| `class-service-registry.php` | 398 |

**Folder Total: 3 files, 2106 LOC**

### 📁 `includes/cache/`

| File Name | LOC |
|-----------|----:|
| `class-cache-warming.php` | 624 |
| `class-cache-manager.php` | 431 |
| `class-reference-data-cache.php` | 379 |
| `class-cache-factory.php` | 69 |

**Folder Total: 4 files, 1503 LOC**

### 📁 `includes/cli/`

| File Name | LOC |
|-----------|----:|
| `class-scd-cli-health-check.php` | 351 |

**Folder Total: 1 files, 351 LOC**

### 📁 `includes/constants/`

| File Name | LOC |
|-----------|----:|
| `class-scd-product-selection-types.php` | 77 |

**Folder Total: 1 files, 77 LOC**

### 📁 `includes/core/analytics/`

| File Name | LOC |
|-----------|----:|
| `class-analytics-collector.php` | 1481 |
| `class-report-generator.php` | 805 |
| `class-analytics-data.php` | 746 |
| `class-metrics-calculator.php` | 645 |
| `class-export-service.php` | 251 |
| `class-activity-tracker.php` | 227 |
| `abstract-analytics-handler.php` | 132 |
| `trait-analytics-helpers.php` | 63 |

**Folder Total: 8 files, 4350 LOC**

### 📁 `includes/core/campaigns/`

| File Name | LOC |
|-----------|----:|
| `class-campaign-manager.php` | 2639 |
| `class-campaign-wizard-controller.php` | 1165 |
| `class-campaign-compiler-service.php` | 832 |
| `class-campaign.php` | 728 |
| `class-campaign-state-manager.php` | 694 |
| `class-campaign-list-controller.php` | 693 |
| `class-campaign-formatter.php` | 510 |
| `class-campaign-view-renderer.php` | 457 |
| `class-campaign-action-handler.php` | 434 |
| `class-campaign-calculator.php` | 431 |
| `class-campaign-event-scheduler.php` | 353 |
| `abstract-campaign-controller.php` | 152 |

**Folder Total: 12 files, 9088 LOC**

### 📁 `includes/core/cron/`

| File Name | LOC |
|-----------|----:|
| `class-cron-scheduler.php` | 301 |

**Folder Total: 1 files, 301 LOC**

### 📁 `includes/core/discounts/`

| File Name | LOC |
|-----------|----:|
| `class-discount.php` | 695 |
| `class-discount-applicator.php` | 625 |
| `class-discount-engine.php` | 501 |
| `interface-discount-strategy.php` | 469 |

**Folder Total: 4 files, 2290 LOC**

### 📁 `includes/core/discounts/strategies/`

| File Name | LOC |
|-----------|----:|
| `class-tiered-strategy.php` | 704 |
| `class-spend-threshold-strategy.php` | 530 |
| `class-percentage-strategy.php` | 358 |
| `class-bogo-strategy.php` | 354 |
| `class-fixed-strategy.php` | 338 |

**Folder Total: 5 files, 2284 LOC**

### 📁 `includes/core/exceptions/`

| File Name | LOC |
|-----------|----:|
| `class-concurrent-modification-exception.php` | 117 |

**Folder Total: 1 files, 117 LOC**

### 📁 `includes/core/managers/`

| File Name | LOC |
|-----------|----:|
| `class-customer-usage-manager.php` | 416 |

**Folder Total: 1 files, 416 LOC**

### 📁 `includes/core/products/`

| File Name | LOC |
|-----------|----:|
| `class-product-selector.php` | 1618 |
| `class-condition-engine.php` | 1073 |
| `class-product-filter.php` | 822 |
| `class-product-service.php` | 492 |

**Folder Total: 4 files, 4005 LOC**

### 📁 `includes/core/scheduling/`

| File Name | LOC |
|-----------|----:|
| `class-action-scheduler-service.php` | 460 |
| `class-task-manager.php` | 158 |

**Folder Total: 2 files, 618 LOC**

### 📁 `includes/core/services/`

| File Name | LOC |
|-----------|----:|
| `class-campaign-health-service.php` | 2075 |
| `class-currency-change-service.php` | 424 |

**Folder Total: 2 files, 2499 LOC**

### 📁 `includes/core/validation/`

| File Name | LOC |
|-----------|----:|
| `class-field-definitions.php` | 2330 |
| `class-validation.php` | 501 |
| `class-wizard-validation.php` | 466 |
| `class-ajax-validation.php` | 259 |
| `class-pro-feature-validator.php` | 240 |
| `class-validation-rules.php` | 107 |

**Folder Total: 6 files, 3903 LOC**

### 📁 `includes/core/wizard/`

| File Name | LOC |
|-----------|----:|
| `class-wizard-state-service.php` | 1107 |
| `class-wizard-manager.php` | 854 |
| `class-campaign-change-tracker.php` | 382 |

**Note**: `class-campaign-health-calculator.php` (1980 LOC) was removed. The wizard now uses the unified `SCD_Campaign_Health_Service` as the single source of truth for all health calculations.
| `class-idempotency-service.php` | 252 |
| `class-step-data-transformer.php` | 242 |
| `class-wizard-field-mapper.php` | 237 |
| `class-wizard-navigation.php` | 221 |
| `class-wizard-step-registry.php` | 216 |
| `class-complete-wizard-handler.php` | 196 |
| `class-wizard-sidebar.php` | 182 |
| `class-sidebar-base.php` | 148 |

**Folder Total: 12 files, 6017 LOC**

### 📁 `includes/database/`

| File Name | LOC |
|-----------|----:|
| `class-database-manager.php` | 552 |
| `class-migration-manager.php` | 516 |
| `class-query-builder.php` | 301 |
| `interface-migration.php` | 45 |

**Folder Total: 4 files, 1414 LOC**

### 📁 `includes/database/migrations/`

| File Name | LOC |
|-----------|----:|
| `001-initial-schema.php` | 674 |
| `006-add-foreign-keys-indexes.php` | 395 |
| `003-float-to-decimal.php` | 173 |
| `004-add-activity-log-table.php` | 144 |
| `002-timezone-update.php` | 119 |
| `005-add-campaign-version-column.php` | 114 |

**Folder Total: 6 files, 1619 LOC**

### 📁 `includes/database/repositories/`

| File Name | LOC |
|-----------|----:|
| `class-campaign-repository.php` | 1563 |
| `class-analytics-repository.php` | 604 |
| `class-base-repository.php` | 587 |
| `class-discount-repository.php` | 423 |
| `class-customer-usage-repository.php` | 422 |

**Folder Total: 5 files, 3599 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/`

| File Name | LOC |
|-----------|----:|
| `start.php` | 629 |
| `config.php` | 391 |
| `require.php` | 62 |
| `index.php` | 2 |

**Folder Total: 4 files, 1084 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/css/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/css/admin/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/img/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/js/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/`

| File Name | LOC |
|-----------|----:|
| `class-freemius.php` | 26358 |
| `fs-plugin-info-dialog.php` | 1691 |
| `class-fs-plugin-updater.php` | 1607 |
| `fs-core-functions.php` | 1505 |
| `class-fs-logger.php` | 728 |
| `class-fs-api.php` | 723 |
| `class-fs-storage.php` | 560 |
| `class-freemius-abstract.php` | 537 |
| `class-fs-garbage-collector.php` | 438 |
| `class-fs-options.php` | 430 |
| `fs-essential-functions.php` | 417 |
| `class-fs-admin-notices.php` | 352 |
| `fs-html-escaping-functions.php` | 126 |
| `class-fs-lock.php` | 109 |
| `class-fs-security.php` | 103 |
| `class-fs-user-lock.php` | 89 |
| `l10n.php` | 48 |
| `class-fs-hook-snapshot.php` | 44 |
| `index.php` | 2 |

**Folder Total: 19 files, 35867 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/customizer/`

| File Name | LOC |
|-----------|----:|
| `class-fs-customizer-upsell-control.php` | 159 |
| `class-fs-customizer-support-section.php` | 101 |
| `index.php` | 2 |

**Folder Total: 3 files, 262 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/debug/`

| File Name | LOC |
|-----------|----:|
| `class-fs-debug-bar-panel.php` | 67 |
| `debug-bar-start.php` | 51 |
| `index.php` | 2 |

**Folder Total: 3 files, 120 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/entities/`

| File Name | LOC |
|-----------|----:|
| `class-fs-plugin-license.php` | 334 |
| `class-fs-site.php` | 294 |
| `class-fs-payment.php` | 167 |
| `class-fs-plugin.php` | 163 |
| `class-fs-entity.php` | 158 |
| `class-fs-plugin-plan.php` | 156 |
| `class-fs-pricing.php` | 156 |
| `class-fs-subscription.php` | 146 |
| `class-fs-affiliate-terms.php` | 131 |
| `class-fs-billing.php` | 94 |
| `class-fs-user.php` | 85 |
| `class-fs-affiliate.php` | 83 |
| `class-fs-plugin-tag.php` | 67 |
| `class-fs-plugin-info.php` | 33 |
| `class-fs-scope-entity.php` | 28 |
| `index.php` | 2 |

**Folder Total: 16 files, 2097 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/managers/`

| File Name | LOC |
|-----------|----:|
| `class-fs-clone-manager.php` | 1670 |
| `class-fs-admin-menu-manager.php` | 1025 |
| `class-fs-permission-manager.php` | 706 |
| `class-fs-admin-notice-manager.php` | 538 |
| `class-fs-debug-manager.php` | 508 |
| `class-fs-option-manager.php` | 476 |
| `class-fs-key-value-storage.php` | 401 |
| `class-fs-cache-manager.php` | 325 |
| `class-fs-checkout-manager.php` | 241 |
| `class-fs-plugin-manager.php` | 232 |
| `class-fs-plan-manager.php` | 192 |
| `class-fs-gdpr-manager.php` | 189 |
| `class-fs-license-manager.php` | 103 |
| `class-fs-contact-form-manager.php` | 83 |
| `index.php` | 2 |

**Folder Total: 15 files, 6691 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/sdk/`

| File Name | LOC |
|-----------|----:|
| `FreemiusWordPress.php` | 745 |
| `FreemiusBase.php` | 216 |
| `index.php` | 2 |

**Folder Total: 3 files, 963 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/sdk/Exceptions/`

| File Name | LOC |
|-----------|----:|
| `Exception.php` | 78 |
| `OAuthException.php` | 16 |
| `ArgumentNotExistException.php` | 13 |
| `EmptyArgumentException.php` | 13 |
| `InvalidArgumentException.php` | 12 |
| `index.php` | 2 |

**Folder Total: 6 files, 134 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/includes/supplements/`

| File Name | LOC |
|-----------|----:|
| `fs-essential-functions-2.2.1.php` | 44 |
| `fs-essential-functions-1.1.7.1.php` | 43 |
| `fs-migration-2.5.1.php` | 30 |
| `index.php` | 2 |

**Folder Total: 4 files, 119 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/languages/`

| File Name | LOC |
|-----------|----:|
| `index.php` | 2 |

**Folder Total: 1 files, 2 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/`

| File Name | LOC |
|-----------|----:|
| `account.php` | 1120 |
| `connect.php` | 1061 |
| `debug.php` | 950 |
| `add-ons.php` | 499 |
| `auto-installation.php` | 249 |
| `tabs.php` | 189 |
| `pricing.php` | 113 |
| `admin-notice.php` | 112 |
| `contact.php` | 104 |
| `clone-resolution-js.php` | 88 |
| `gdpr-optin-js.php` | 66 |
| `tabs-capture-js.php` | 63 |
| `email.php` | 48 |
| `sticky-admin-notice-js.php` | 40 |
| `secure-https-header.php` | 38 |
| `api-connectivity-message-js.php` | 31 |
| `add-trial-to-pricing.php` | 30 |
| `checkout.php` | 27 |
| `plugin-icon.php` | 21 |
| `ajax-loader.php` | 6 |
| `index.php` | 2 |

**Folder Total: 21 files, 4857 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/account/`

| File Name | LOC |
|-----------|----:|
| `billing.php` | 422 |
| `payments.php` | 58 |
| `index.php` | 2 |

**Folder Total: 3 files, 482 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/account/partials/`

| File Name | LOC |
|-----------|----:|
| `addon.php` | 451 |
| `site.php` | 353 |
| `disconnect-button.php` | 103 |
| `activate-license-button.php` | 53 |
| `deactivate-license-button.php` | 35 |
| `index.php` | 2 |

**Folder Total: 6 files, 997 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/checkout/`

| File Name | LOC |
|-----------|----:|
| `frame.php` | 181 |
| `process-redirect.php` | 129 |
| `redirect.php` | 102 |

**Folder Total: 3 files, 412 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/connect/`

| File Name | LOC |
|-----------|----:|
| `permissions-group.php` | 71 |
| `permission.php` | 42 |
| `index.php` | 2 |

**Folder Total: 3 files, 115 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/debug/`

| File Name | LOC |
|-----------|----:|
| `api-calls.php` | 154 |
| `scheduled-crons.php` | 147 |
| `plugins-themes-sync.php` | 76 |
| `logger.php` | 65 |
| `index.php` | 2 |

**Folder Total: 5 files, 444 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/forms/`

| File Name | LOC |
|-----------|----:|
| `license-activation.php` | 899 |
| `affiliation.php` | 510 |
| `email-address-update.php` | 346 |
| `user-change.php` | 296 |
| `subscription-cancellation.php` | 282 |
| `resend-key.php` | 250 |
| `data-debug-mode.php` | 212 |
| `premium-versions-upgrade-handler.php` | 204 |
| `optout.php` | 183 |
| `trial-start.php` | 181 |
| `premium-versions-upgrade-metadata.php` | 46 |
| `index.php` | 2 |

**Folder Total: 12 files, 3411 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/forms/deactivation/`

| File Name | LOC |
|-----------|----:|
| `form.php` | 666 |
| `contact.php` | 23 |
| `retry-skip.php` | 23 |
| `index.php` | 2 |

**Folder Total: 4 files, 714 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/js/`

| File Name | LOC |
|-----------|----:|
| `permissions.php` | 545 |
| `jquery.content-change.php` | 57 |
| `style-premium-theme.php` | 52 |
| `open-license-activation.php` | 36 |
| `index.php` | 2 |

**Folder Total: 5 files, 692 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/partials/`

| File Name | LOC |
|-----------|----:|
| `network-activation.php` | 94 |
| `index.php` | 2 |

**Folder Total: 2 files, 96 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/templates/plugin-info/`

| File Name | LOC |
|-----------|----:|
| `features.php` | 112 |
| `description.php` | 72 |
| `screenshots.php` | 29 |
| `index.php` | 2 |

**Folder Total: 4 files, 215 LOC**

### 📁 `includes/frontend/`

| File Name | LOC |
|-----------|----:|
| `class-frontend-asset-manager.php` | 574 |
| `class-shortcodes.php` | 363 |
| `class-frontend-manager.php` | 242 |
| `class-template-loader.php` | 219 |
| `class-discount-display.php` | 176 |
| `class-countdown-timer.php` | 109 |

**Folder Total: 6 files, 1683 LOC**

### 📁 `includes/frontend/assets/`

| File Name | LOC |
|-----------|----:|
| `class-frontend-script-registry.php` | 246 |

**Folder Total: 1 files, 246 LOC**

### 📁 `includes/integrations/`

| File Name | LOC |
|-----------|----:|
| `class-integration-manager.php` | 213 |

**Folder Total: 1 files, 213 LOC**

### 📁 `includes/integrations/blocks/`

| File Name | LOC |
|-----------|----:|
| `class-blocks-manager.php` | 742 |

**Folder Total: 1 files, 742 LOC**

### 📁 `includes/integrations/email/`

| File Name | LOC |
|-----------|----:|
| `class-email-manager.php` | 1359 |
| `interface-email-provider.php` | 82 |

**Folder Total: 2 files, 1441 LOC**

### 📁 `includes/integrations/email/providers/`

| File Name | LOC |
|-----------|----:|
| `class-amazonses-provider.php` | 422 |
| `class-sendgrid-provider.php` | 375 |
| `class-wpmail-provider.php` | 288 |

**Folder Total: 3 files, 1085 LOC**

### 📁 `includes/integrations/woocommerce/`

| File Name | LOC |
|-----------|----:|
| `class-wc-discount-query-service.php` | 475 |
| `class-woocommerce-integration.php` | 446 |
| `class-wc-display-integration.php` | 394 |
| `class-wc-price-integration.php` | 362 |
| `class-wc-blocks-integration.php` | 329 |
| `class-wc-order-integration.php` | 170 |
| `class-wc-cart-message-service.php` | 153 |
| `class-wc-admin-integration.php` | 125 |

**Folder Total: 8 files, 2454 LOC**

### 📁 `includes/security/`

| File Name | LOC |
|-----------|----:|
| `class-security-manager.php` | 147 |
| `class-audit-logger.php` | 142 |
| `class-rate-limiter.php` | 98 |
| `class-nonce-manager.php` | 81 |
| `interface-nonce-manager.php` | 60 |

**Folder Total: 5 files, 528 LOC**

### 📁 `includes/services/`

| File Name | LOC | Description |
|-----------|----:|-------------|
| `class-dashboard-service.php` | 903 | Dashboard orchestrator - coordinates dashboard data assembly, caching, and health monitoring |
| `class-campaign-creator-service.php` | 673 | Campaign creation and management service |
| `class-campaign-suggestions-service.php` | 527 | Event-based campaign suggestions with intelligent timing windows |
| `class-campaign-display-service.php` | 419 | Campaign display preparation with computed fields (time remaining, urgency, status) |

**Folder Total: 4 files, 2,522 LOC**

**Architecture Notes:**
- **Dashboard Service** acts as orchestrator, delegating to specialized sub-services
- **Campaign Suggestions Service** handles all seasonal event suggestions and timing calculations
- **Campaign Display Service** prepares campaigns for UI display with pre-computed fields
- Services follow Single Responsibility Principle for better testability and maintainability
- All services registered in `includes/bootstrap/class-service-definitions.php`

### 📁 `includes/utilities/`

| File Name | LOC |
|-----------|----:|
| `class-session-service.php` | 1026 |
| `class-logger.php` | 768 |
| `class-debug-logger.php` | 696 |
| `class-service-health-check.php` | 540 |
| `class-time-helpers.php` | 512 |
| `class-debug-console.php` | 509 |
| `class-requirements-checker.php` | 449 |
| `scd-debug-console-functions.php` | 442 |
| `class-performance-optimizer.php` | 424 |
| `class-session-lock-service.php` | 406 |
| `class-error-handler.php` | 387 |
| `class-log-manager.php` | 362 |
| `class-performance-monitor.php` | 317 |
| `scd-debug-functions.php` | 295 |
| `class-performance-bootstrapper.php` | 273 |
| `class-factory-helper.php` | 263 |
| `class-datetime-builder.php` | 229 |
| `class-translation-handler.php` | 167 |
| `class-datetime-splitter.php` | 138 |
| `class-scd-log.php` | 128 |
| `class-case-converter.php` | 121 |
| `class-campaign-schedule-validator.php` | 108 |
| `class-theme-colors.php` | 102 |

**Folder Total: 23 files, 8662 LOC**

### 📁 `includes/utilities/traits/`

| File Name | LOC |
|-----------|----:|
| `trait-admin-notice.php` | 438 |

**Folder Total: 1 files, 438 LOC**

### 📁 `resources/views/admin/components/`

| File Name | LOC |
|-----------|----:|
| `header.php` | 65 |
| `footer.php` | 31 |

**Folder Total: 2 files, 96 LOC**

### 📁 `resources/views/admin/pages/`

| File Name | LOC |
|-----------|----:|
| `dashboard.php` | 461 |
| `campaign-performance.php` | 444 |
| `currency-review.php` | 252 |

**Folder Total: 3 files, 1157 LOC**

### 📁 `resources/views/admin/pages/dashboard/`

| File Name | LOC |
|-----------|----:|
| `main-dashboard.php` | 1053 |

**Folder Total: 1 files, 1053 LOC**

### 📁 `resources/views/admin/partials/`

| File Name | LOC |
|-----------|----:|
| `pro-feature-modal.php` | 163 |
| `pro-feature-overlay.php` | 56 |

**Folder Total: 2 files, 219 LOC**

### 📁 `resources/views/admin/wizard/`

| File Name | LOC |
|-----------|----:|
| `step-review.php` | 1272 |
| `step-discounts.php` | 1224 |
| `template-wrapper.php` | 646 |
| `step-products.php` | 603 |
| `step-schedule.php` | 596 |
| `sidebar-schedule.php` | 262 |
| `sidebar-discounts.php` | 261 |
| `sidebar-review.php` | 216 |
| `sidebar-basic.php` | 202 |
| `step-basic.php` | 151 |
| `wizard-navigation.php` | 108 |
| `sidebar-products.php` | 87 |

**Folder Total: 12 files, 5628 LOC**

### 📁 `templates/emails/`

| File Name | LOC |
|-----------|----:|
| `milestone-alert.php` | 267 |
| `low-stock-alert.php` | 262 |
| `performance-alert.php` | 246 |
| `daily-report.php` | 230 |
| `campaign-ending.php` | 226 |
| `weekly-report.php` | 216 |
| `campaign-ended.php` | 212 |
| `campaign-started.php` | 193 |

**Folder Total: 8 files, 1852 LOC**


## JavaScript Files

### 📁 `assets/js/`

| File Name | LOC |
|-----------|----:|
| `scd-tooltips.js` | 315 |

**Folder Total: 1 files, 315 LOC**

### 📁 `assets/js/admin/`

| File Name | LOC |
|-----------|----:|
| `notifications-settings.js` | 360 |
| `queue-management.js` | 219 |

**Folder Total: 2 files, 579 LOC**

### 📁 `assets/js/utilities/`

| File Name | LOC |
|-----------|----:|
| `debug-console.js` | 415 |

**Folder Total: 1 files, 415 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/js/`

| File Name | LOC |
|-----------|----:|
| `nojquery.ba-postmessage.js` | 27 |
| `jquery.form.js` | 0 |
| `postmessage.js` | 0 |

**Folder Total: 3 files, 27 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/js/pricing/`

| File Name | LOC |
|-----------|----:|
| `freemius-pricing.js` | 1 |

**Folder Total: 1 files, 1 LOC**

### 📁 `resources/assets/js/admin/`

| File Name | LOC |
|-----------|----:|
| `ajax-service.js` | 919 |
| `tools.js` | 783 |
| `notification-service.js` | 557 |
| `ui-utilities.js` | 471 |
| `admin.js` | 415 |
| `campaigns-list.js` | 320 |
| `settings-advanced.js` | 176 |
| `system-check.js` | 172 |
| `settings-performance.js` | 123 |
| `settings-general.js` | 99 |
| `console-logger.js` | 97 |
| `upgrade-banner-dismiss.js` | 72 |
| `init-shared.js` | 42 |
| `bulk-actions.js` | 19 |

**Folder Total: 14 files, 4265 LOC**

### 📁 `resources/assets/js/admin/dashboard/`

| File Name | LOC |
|-----------|----:|
| `main-dashboard.js` | 372 |

**Folder Total: 1 files, 372 LOC**

### 📁 `resources/assets/js/analytics/`

| File Name | LOC |
|-----------|----:|
| `analytics-dashboard.js` | 1017 |
| `timeline-visualizer.js` | 826 |
| `theme-color-init.js` | 151 |
| `scd-analytics-tracking.js` | 103 |

**Folder Total: 4 files, 2097 LOC**

### 📁 `resources/assets/js/components/`

| File Name | LOC |
|-----------|----:|
| `date-time-picker.js` | 762 |
| `init.js` | 35 |

**Folder Total: 2 files, 797 LOC**

### 📁 `resources/assets/js/constants/`

| File Name | LOC |
|-----------|----:|
| `product-selection-types.js` | 55 |

**Folder Total: 1 files, 55 LOC**

### 📁 `resources/assets/js/frontend/`

| File Name | LOC |
|-----------|----:|
| `main.js` | 230 |

**Folder Total: 1 files, 230 LOC**

### 📁 `resources/assets/js/shared/`

| File Name | LOC |
|-----------|----:|
| `utils.js` | 1449 |
| `tom-select-base.js` | 1021 |
| `base-orchestrator.js` | 907 |
| `base-state.js` | 367 |
| `module-loader.js` | 342 |
| `theme-color-service.js` | 306 |
| `error-handler.js` | 290 |
| `theme-color-init.js` | 268 |
| `event-manager-mixin.js` | 265 |
| `base-api.js` | 208 |
| `module-utilities.js` | 188 |
| `field-definitions.js` | 163 |
| `debug-logger.js` | 134 |

**Folder Total: 13 files, 5908 LOC**

### 📁 `resources/assets/js/shared/mixins/`

| File Name | LOC |
|-----------|----:|
| `step-persistence.js` | 951 |

**Folder Total: 1 files, 951 LOC**

### 📁 `resources/assets/js/steps/basic/`

| File Name | LOC |
|-----------|----:|
| `basic-fields.js` | 301 |
| `basic-state.js` | 187 |
| `basic-orchestrator.js` | 146 |
| `basic-api.js` | 70 |

**Folder Total: 4 files, 704 LOC**

### 📁 `resources/assets/js/steps/discounts/`

| File Name | LOC |
|-----------|----:|
| `tiered-discount.js` | 1048 |
| `spend-threshold.js` | 935 |
| `discounts-state.js` | 784 |
| `bogo-discount.js` | 741 |
| `discounts-orchestrator.js` | 695 |
| `discounts-integration.js` | 690 |
| `discounts-conditions.js` | 658 |
| `discounts-type-registry.js` | 488 |
| `discounts-config.js` | 330 |
| `fixed-discount.js` | 314 |
| `percentage-discount.js` | 286 |
| `complex-field-handler.js` | 271 |
| `base-discount.js` | 248 |
| `discounts-api.js` | 101 |

**Folder Total: 14 files, 7589 LOC**

### 📁 `resources/assets/js/steps/products/`

| File Name | LOC |
|-----------|----:|
| `products-picker.js` | 1112 |
| `products-orchestrator.js` | 901 |
| `products-api.js` | 427 |
| `products-state.js` | 246 |

**Folder Total: 4 files, 2686 LOC**

### 📁 `resources/assets/js/steps/review/`

| File Name | LOC |
|-----------|----:|
| `review-components.js` | 424 |
| `review-orchestrator.js` | 325 |
| `review-state.js` | 178 |
| `review-api.js` | 99 |

**Folder Total: 4 files, 1026 LOC**

### 📁 `resources/assets/js/steps/schedule/`

| File Name | LOC |
|-----------|----:|
| `schedule-orchestrator.js` | 1106 |
| `schedule-state.js` | 431 |
| `schedule-config.js` | 323 |
| `schedule-debug.js` | 213 |
| `schedule-api.js` | 209 |

**Folder Total: 5 files, 2282 LOC**

### 📁 `resources/assets/js/validation/`

| File Name | LOC |
|-----------|----:|
| `validation-manager.js` | 1054 |
| `validation-error.js` | 926 |

**Folder Total: 2 files, 1980 LOC**

### 📁 `resources/assets/js/wizard/`

| File Name | LOC |
|-----------|----:|
| `wizard-orchestrator.js` | 1495 |
| `wizard-navigation.js` | 1364 |
| `wizard-persistence-service.js` | 1270 |
| `review-health-check.js` | 1157 |
| `wizard-state-manager.js` | 665 |
| `wizard.js` | 658 |
| `wizard-lifecycle.js` | 558 |
| `wizard-event-bus.js` | 447 |
| `wizard-completion-modal.js` | 389 |
| `wizard-save-indicator.js` | 258 |
| `wizard-session-monitor.js` | 246 |
| `sidebar-collapse.js` | 187 |
| `step-loader-factory.js` | 162 |
| `step-registry.js` | 93 |
| `step-config.js` | 86 |
| `step-bridge.js` | 79 |
| `review-sidebar.js` | 35 |

**Folder Total: 17 files, 9149 LOC**

### 📁 `resources/assets/vendor/` (Bundled Libraries)

> **Note:** All vendor libraries are bundled locally for WordPress.org compliance.

| Directory | Library | Version | Files |
|-----------|---------|---------|-------|
| `chart-js/` | Chart.js | 4.5.1 | `chart.umd.min.js` |
| `tom-select/` | Tom Select | 2.4.3 | `tom-select.complete.min.js`, `tom-select.min.css` |

**Folder Total: 3 vendor files (bundled locally)**

### 📁 `tests/javascript/manual/`

| File Name | LOC |
|-----------|----:|
| `tom-select-integration-test.js` | 471 |

**Folder Total: 1 files, 471 LOC**


## CSS/SCSS Files

### 📁 `assets/css/admin/`

| File Name | LOC |
|-----------|----:|
| `notifications.css` | 560 |
| `debug-console.css` | 372 |

**Folder Total: 2 files, 932 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/css/`

| File Name | LOC |
|-----------|----:|
| `customizer.css` | 0 |

**Folder Total: 1 files, 0 LOC**

### 📁 `includes/freemius/wordpress-sdk-master/assets/css/admin/`

| File Name | LOC |
|-----------|----:|
| `account.css` | 0 |
| `add-ons.css` | 0 |
| `affiliation.css` | 0 |
| `checkout.css` | 0 |
| `clone-resolution.css` | 0 |
| `common.css` | 0 |
| `connect.css` | 0 |
| `debug.css` | 0 |
| `dialog-boxes.css` | 0 |
| `gdpr-optin-notice.css` | 0 |
| `optout.css` | 0 |
| `plugins.css` | 0 |

**Folder Total: 12 files, 0 LOC**

### 📁 `resources/assets/css/admin/`

| File Name | LOC |
|-----------|----:|
| `step-discounts.css` | 2060 |
| `step-schedule.css` | 1734 |
| `analytics.css` | 1209 |
| `step-products.css` | 965 |
| `wizard-fullscreen.css` | 871 |
| `wizard-steps.css` | 854 |
| `step-review.css` | 767 |
| `pro-feature-modal.css` | 623 |
| `admin.css` | 558 |
| `step-basic.css` | 448 |
| `wizard-navigation.css` | 447 |
| `wizard-completion-modal.css` | 444 |
| `tom-select-custom.css` | 442 |
| `step-basic-sidebar.css` | 414 |
| `settings.css` | 413 |
| `notifications.css` | 358 |
| `analytics-upgrade.css` | 356 |
| `tools.css` | 294 |
| `validation-ui.css` | 260 |
| `step-schedule-sidebar.css` | 235 |
| `dashboard-upgrade-banner.css` | 230 |
| `campaigns-list.css` | 229 |
| `step-products-sidebar.css` | 229 |
| `wordpress-color-schemes.css` | 205 |
| `validation.css` | 200 |
| `step-discounts-sidebar.css` | 183 |
| `session-expiration-modal.css` | 170 |
| `step-review-sidebar.css` | 155 |
| `tooltips.css` | 47 |

**Folder Total: 29 files, 15400 LOC**

### 📁 `resources/assets/css/admin/dashboard/`

| File Name | LOC |
|-----------|----:|
| `main-dashboard.css` | 3131 |

**Folder Total: 1 files, 3131 LOC**

### 📁 `resources/assets/css/frontend/`

| File Name | LOC |
|-----------|----:|
| `frontend.css` | 58 |

**Folder Total: 1 files, 58 LOC**

### 📁 `resources/assets/css/shared/`

| File Name | LOC |
|-----------|----:|
| `_components.css` | 678 |
| `_utilities.css` | 476 |
| `_forms.css` | 338 |
| `_buttons.css` | 323 |
| `_badges.css` | 301 |
| `pro-feature-unavailable.css` | 215 |
| `_variables.css` | 205 |
| `_theme-colors.css` | 142 |

**Folder Total: 8 files, 2678 LOC**

### 📁 `resources/assets/scss/`

| File Name | LOC |
|-----------|----:|
| `style.scss` | 0 |

**Folder Total: 1 files, 0 LOC**

### 📁 `resources/assets/vendor/` (CSS - External via CDN)

> CSS for external libraries loaded from CDN - see JavaScript section for details.

**Folder Total: 0 local CSS files (CDN-based)**


---

## Summary Statistics

| File Type | File Count | Total LOC | Avg LOC/File |
|-----------|----------:|----------:|-------------:|
| **PHP** | 418 | 179582 | 429 |
| **JavaScript** | 98 | 42426 | 432 |
| **CSS/SCSS** | 57 | 22280 | 390 |
| **TOTAL** | **573** | **244288** | **426** |

---

## Top 30 Largest Files

| # | File Name | Type | LOC |
|--:|-----------|------|----:|
| 1 | `class-freemius.php` | PHP | 26358 |
| 2 | `main-dashboard.css` | CSS | 3131 |
| 3 | `class-campaign-manager.php` | PHP | 2639 |
| 4 | `class-field-definitions.php` | PHP | 2330 |
| 5 | `class-campaign-health-service.php` | PHP | 2075 |
| 6 | `step-discounts.css` | CSS | 2060 |
| 7 | `step-schedule.css` | CSS | 1734 |
| 9 | `class-main-dashboard-page.php` | PHP | 1714 |
| 10 | `fs-plugin-info-dialog.php` | PHP | 1691 |
| 11 | `class-fs-clone-manager.php` | PHP | 1670 |
| 12 | `class-campaigns-list-table.php` | PHP | 1631 |
| 13 | `class-product-selector.php` | PHP | 1618 |
| 14 | `class-fs-plugin-updater.php` | PHP | 1607 |
| 15 | `class-campaign-repository.php` | PHP | 1563 |
| 16 | `fs-core-functions.php` | PHP | 1505 |
| 17 | `wizard-orchestrator.js` | JS | 1495 |
| 18 | `class-analytics-collector.php` | PHP | 1481 |
| 19 | `utils.js` | JS | 1449 |
| 20 | `wizard-navigation.js` | JS | 1364 |
| 21 | `class-email-manager.php` | PHP | 1359 |
| 22 | `class-asset-localizer.php` | PHP | 1336 |
| 23 | `step-review.php` | PHP | 1272 |
| 24 | `wizard-persistence-service.js` | JS | 1270 |
| 25 | `step-discounts.php` | PHP | 1224 |
| 26 | `analytics.css` | CSS | 1209 |
| 27 | `class-campaign-wizard-controller.php` | PHP | 1165 |
| 28 | `review-health-check.js` | JS | 1157 |
| 29 | `account.php` | PHP | 1120 |
| 30 | `class-script-registry.php` | PHP | 1115 |

---

_Generated by analyze-plugin-structure.sh v5_
