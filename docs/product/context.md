# Context

- Domain: migration from legacy Yii-style models to Doctrine mappings.
- Primary inputs: `sandbox/models` (runtime copy), `tests/_data/mock/database/schema.sql`, YAML config.
- Primary outputs: `sandbox/doctrine/*.orm.xml`, updated `sandbox/models/*.php` (in-place AST), `sandbox/doctrine/mismatch-report.txt`.
- AI layer context: supervised pilot in closed contour with weak local model.
