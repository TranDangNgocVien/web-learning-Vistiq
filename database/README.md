# Vistiq database schema

## MySQL connection

Laravel reads connection settings from `.env` through `config/database.php`. The MySQL and MariaDB connections use `utf8mb4` and InnoDB so foreign keys and transactions work consistently. `.env.example` is configured for a Laragon MySQL database named `vistiq`; local credentials stay in `.env`.

The local Laragon MySQL database `vistiq` has been created and all migrations have been applied. The checked-in `schema.sql` is a structure-only export of that database; `data.sql` contains the imported English–Vietnamese dictionary data.

The dictionary import contains 104,829 headwords, 175,735 meanings, 175,735 English examples, 1,502 explicitly tagged idioms, and 1,945 inferred phrasal-verb entries. The source provides no Vietnamese example translations, so those columns are `NULL`. Since this schema stores one IPA per headword, the import keeps a preferred pronunciation (British variants first where available). The source does not mark phrasal verbs as a separate type; those rows are inferred from verb phrases and a matching base headword.

The dictionary is derived from Skypedia's `english-vietnamese-dictionary`, based on MinhQND Dictionary, and is licensed under CC BY-SA 4.0. Preserve the source `ATTRIBUTION.md` if redistributing this derived dataset.

## Table count

The current schema contains **43 physical tables, including `migrations`**. See [CONSOLIDATION.md](CONSOLIDATION.md) for the complete retained-table list, source-to-destination mapping, verified migration command and rollback procedure.

The supplied October 18 SQL dump has 78 tables, or 76 after excluding the discontinued subscription feature. The October 19 forward migration transfers all remaining child/catalog data before removing 35 source tables, including the two subscription tables. Dictionary examples, meanings, IPA, audio, revisions, attempt snapshots and scores remain available through the updated storage contract.

`data.sql` is a legacy dictionary seed for the old schema. Do not import it directly into the consolidated schema. Existing installations migrate their already-imported dictionary in place; the original legacy seed and its attribution are retained unchanged.

## Migrations

| Migration | Tables |
| --- | --- |
| `2026_10_02_121057_create_user_access_tables` | roles, role_user, user_profiles, user_preferences |
| `2026_10_02_121058_create_learning_content_tables` | 20 learning and knowledge tables |
| `2026_10_02_121059_create_assessment_catalog_tables` | 16 exam and question-bank tables |
| `2026_10_02_121100_create_attempt_review_and_grading_tables` | 12 attempt, grading, and report tables |
| `2026_10_02_121101_create_user_engagement_tables` | user_notifications, achievement_definitions, user_achievements |

The three original Laravel migrations create the initial user, cache, and queue infrastructure.

The subsequent migration `2026_10_19_120000_consolidate_vistiq_tables` consolidates the complete historical migration chain to 43 physical tables.
