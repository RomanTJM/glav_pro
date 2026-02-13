SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Seed: demo companies at different stages
INSERT INTO companies (name, stage) VALUES
    ('ООО "Альфа Технологии"', 'C0'),
    ('ЗАО "Бета Консалтинг"', 'C1'),
    ('ИП Иванов', 'C2');

-- Events for "Бета Консалтинг" (stage C1 — was contacted)
INSERT INTO company_events (company_id, event_type, event_data) VALUES
    (2, 'contact_attempt', '{"method": "phone", "comment": "Не дозвонились"}');

-- Events for "ИП Иванов" (stage C2 — has LPR conversation)
INSERT INTO company_events (company_id, event_type, event_data) VALUES
    (3, 'contact_attempt', '{"method": "phone", "comment": "Набрали номер"}'),
    (3, 'lpr_conversation', '{"comment": "Поговорили с директором, интерес есть"}');

-- Transitions
INSERT INTO stage_transitions (company_id, from_stage, to_stage) VALUES
    (2, 'C0', 'C1'),
    (3, 'C0', 'C1'),
    (3, 'C1', 'C2');
