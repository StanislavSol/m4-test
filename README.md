# M4 API Клиент

Консольный скрипт для тестового задания. Работает с API M4 Systems.

## Быстрый старт

```bash
# 1. Склонировать проект
git clone git@github.com:StanislavSol/m4-test.git
cd m4-test

# 2. Установить зависимости
make install

# 3. Создать файл .env и указать логин с паролем
cp .env.example .env
# или вручную:
echo "M4_LOGIN=твой_логин" > .env
echo "M4_PASSWORD=твой_пароль" >> .env

# 4. Запустить
make run
