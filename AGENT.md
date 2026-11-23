https://github.com/romicharroyo/local_zoo_for_vyatgu - репозиторий, с которым надо будет работать

# Задаие №1

[29 09 2026 11:12] Philipp Bolotov: #домашка

Создать публичный проект в Гитхабе и закоммитить скелет приложения с основными библиотеками.

Реализовать подключение  Eloquent и мигратор с тестовой миграцией на его основе.
Добавить логирование средствами Monolog.
В index.php перейти на  Eloquent и добавить логирование каждого входящего http-запроса с телом ответа.
[29 09 2026 13:24] Philipp Bolotov: Хорошая инструкция/мануал к Монологу: https://seldaek.github.io/monolog/doc/01-usage.html

Коммиты на гитхаб делай сам, вот репо:
…or create a new repository on the command line

echo "# web_1" >> README.md
git init
git add README.md
git commit -m "first commit"
git branch -M main
git remote add origin https://github.com/bruress/web_1.git
git push -u origin main

…or push an existing repository from the command line

git remote add origin https://github.com/bruress/web_1.git
git branch -M main
git push -u origin main
