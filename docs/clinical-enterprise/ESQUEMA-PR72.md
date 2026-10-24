# DDL exacto — esquema local descartable

Generado por SHOW CREATE TABLE en MariaDB11.8.9, puerto33345. No es un dump productivo ni contiene filas. Los nombres de tablas padre se conservan.

## patient_operational_alerts

```sql
CREATE TABLE `patient_operational_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `category` varchar(40) NOT NULL,
  `incident_on` date NOT NULL,
  `reason_ciphertext` text NOT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_operational_alerts_patient_id_unique` (`patient_id`),
  KEY `patient_operational_alerts_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `patient_operational_alerts_updated_by_user_id_foreign` (`updated_by_user_id`),
  CONSTRAINT `patient_operational_alerts_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `patient_operational_alerts_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `patient_operational_alerts_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## patient_operational_alert_events

```sql
CREATE TABLE `patient_operational_alert_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `alert_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned NOT NULL,
  `request_key` char(36) NOT NULL,
  `action` varchar(16) NOT NULL,
  `snapshot_ciphertext` mediumtext NOT NULL,
  `occurred_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `poa_events_actor_request_unique` (`actor_user_id`,`request_key`),
  KEY `patient_operational_alert_events_alert_id_foreign` (`alert_id`),
  KEY `patient_operational_alert_events_appointment_id_foreign` (`appointment_id`),
  CONSTRAINT `patient_operational_alert_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `patient_operational_alert_events_alert_id_foreign` FOREIGN KEY (`alert_id`) REFERENCES `patient_operational_alerts` (`id`),
  CONSTRAINT `patient_operational_alert_events_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## medical_procedure_followups

```sql
CREATE TABLE `medical_procedure_followups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `requested_procedure_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `doctor_id` bigint(20) unsigned NOT NULL,
  `procedure_family` varchar(32) NOT NULL,
  `procedure_label` varchar(500) NOT NULL,
  `requested_at` datetime NOT NULL,
  `source_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(24) NOT NULL DEFAULT 'PENDING',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `planned_review_on` date DEFAULT NULL,
  `notes_ciphertext` text DEFAULT NULL,
  `updated_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `origin` varchar(16) NOT NULL DEFAULT 'AUTOMATIC',
  `manual_reason_ciphertext` mediumtext DEFAULT NULL,
  `general_key` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpf_request_unique` (`requested_procedure_id`),
  UNIQUE KEY `mpf_general_unique` (`doctor_id`,`appointment_id`,`general_key`),
  KEY `medical_procedure_followups_appointment_id_foreign` (`appointment_id`),
  KEY `medical_procedure_followups_patient_id_foreign` (`patient_id`),
  KEY `medical_procedure_followups_updated_by_user_id_foreign` (`updated_by_user_id`),
  KEY `mpf_doctor_queue` (`doctor_id`,`status`,`requested_at`),
  CONSTRAINT `medical_procedure_followups_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `medical_procedure_followups_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `medical_procedure_followups_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `medical_procedure_followups_requested_procedure_id_foreign` FOREIGN KEY (`requested_procedure_id`) REFERENCES `appointment_requested_procedures` (`id`),
  CONSTRAINT `medical_procedure_followups_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## medical_procedure_followup_events

```sql
CREATE TABLE `medical_procedure_followup_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `followup_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned NOT NULL,
  `request_key` char(36) NOT NULL,
  `action` varchar(24) NOT NULL,
  `snapshot_ciphertext` mediumtext NOT NULL,
  `occurred_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpfe_operation_unique` (`followup_id`,`actor_user_id`,`request_key`),
  KEY `medical_procedure_followup_events_actor_user_id_foreign` (`actor_user_id`),
  CONSTRAINT `medical_procedure_followup_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `medical_procedure_followup_events_followup_id_foreign` FOREIGN KEY (`followup_id`) REFERENCES `medical_procedure_followups` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## doctor_procedure_suggestions

```sql
CREATE TABLE `doctor_procedure_suggestions` (
  `id` char(36) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `doctor_id` bigint(20) unsigned NOT NULL,
  `normalized_hash` varchar(64) NOT NULL,
  `label_ciphertext` mediumtext NOT NULL,
  `procedure_family` varchar(32) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dps_owner_name_unique` (`user_id`,`doctor_id`,`normalized_hash`),
  KEY `doctor_procedure_suggestions_doctor_id_foreign` (`doctor_id`),
  CONSTRAINT `doctor_procedure_suggestions_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `doctor_procedure_suggestions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## appointment_requested_procedures

```sql
CREATE TABLE `appointment_requested_procedures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `procedure_key` varchar(40) NOT NULL,
  `selected` tinyint(1) NOT NULL DEFAULT 1,
  `requested_by_user_id` bigint(20) unsigned NOT NULL,
  `requested_at` timestamp NOT NULL,
  `verified_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `custom_label` varchar(500) DEFAULT NULL,
  `procedure_family` varchar(32) DEFAULT NULL,
  `label_snapshot` varchar(500) DEFAULT NULL,
  `request_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_details`)),
  `reusable_procedure_id` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_procedure_unique` (`appointment_id`,`procedure_key`),
  KEY `appointment_requested_procedures_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `appointment_requested_procedures_verified_by_user_id_foreign` (`verified_by_user_id`),
  KEY `appointment_requested_procedures_reusable_procedure_id_foreign` (`reusable_procedure_id`),
  CONSTRAINT `appointment_requested_procedures_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `appointment_requested_procedures_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `appointment_requested_procedures_reusable_procedure_id_foreign` FOREIGN KEY (`reusable_procedure_id`) REFERENCES `doctor_procedure_suggestions` (`id`),
  CONSTRAINT `appointment_requested_procedures_verified_by_user_id_foreign` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## appointment_operational_reviews

```sql
CREATE TABLE `appointment_operational_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 0,
  `requested_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `completed_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` varchar(24) NOT NULL DEFAULT 'NOT_READY',
  `origin` varchar(16) DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `decided_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `clinical_order_ciphertext` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_operational_reviews_appointment_id_unique` (`appointment_id`),
  KEY `appointment_operational_reviews_decided_by_user_id_foreign` (`decided_by_user_id`),
  KEY `operational_review_queue` (`status`,`ready_at`,`id`),
  CONSTRAINT `appointment_operational_reviews_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `appointment_operational_reviews_decided_by_user_id_foreign` FOREIGN KEY (`decided_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

