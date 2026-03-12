# Context

- Domain: migration from legacy Yii-style models to Doctrine mappings.
- Primary inputs: `tests/_data/mock/models`, `tests/_data/mock/database/schema.sql`, YAML config.
- Primary outputs: `generated/doctrine/*.orm.xml`, `generated/classes/*.php`, `generated/doctrine/mismatch-report.txt`.
- AI layer context: supervised pilot in closed contour with weak local model.
