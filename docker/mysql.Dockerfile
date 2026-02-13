FROM mysql:8.0

# Настройка UTF-8 для всех соединений
RUN printf '[mysqld]\n\
character-set-server=utf8mb4\n\
collation-server=utf8mb4_unicode_ci\n\
\n\
[client]\n\
default-character-set=utf8mb4\n' > /etc/mysql/conf.d/charset.cnf

# Копируем SQL-миграции для инициализации БД
COPY migrations/001_schema.sql /docker-entrypoint-initdb.d/001_schema.sql
COPY migrations/002_seed.sql /docker-entrypoint-initdb.d/002_seed.sql
