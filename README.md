# Загрузка продукции для таблиц Fluid-line.ru

## Requirement's
+ PHP >= 8.0
+ MySQL
+ Webpack

## Symfony Command's
Ниже представлен набор команд, которые взаимодействуют с данной системой, 
прошу учесть, что использование каждой команды может повлечь за собой
непредвиденные последствия. Для использования, необходимо использовать 
`Symfony Console`

### Crawler
Команда Crawler представляет собой обработчик-конвертер продукции, 
которая состоит из CSV файлов и заложена в проекте
<a href="https://github.com/Lavreek/Fluidline.InventoryProducts.git">продукции</a>.
Сериализует контент файла в entity объекты данной системы.
### Persist
Команда Persist - загрузчик сериализованных entity объектов 
в базу данных
### ImagesPuller
Команда ImagesPuller - загрузчик изображений для объектов базы данных.
### PricesPuller
Команда PricesPuller - загрузчик цен для объектов базы данных.
### Remove
Команда Remove - команда удаления серии.

## Таблица опций

<table>
<thead>
<th>Опция</th>
<th>Crawler</th>
<th>Persist</th>
<th>ImagesPuller</th>
<th>PricesPuller</th>
<th>Remove</th>
</thead>
<tbody>

<tr>
<td>--type</td>
<td colspan="5">
Опция type представляет собой сущность категории продукции, например, "Фитинги".
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
<td>Да</td>
</tr>

<tr>
<td>
--serial
</td>
<td colspan="5">
Опция serial представляет собой сущность серии продукции, например, "CUA".
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
</tr>

<tr>
<td>
--file
</td>
<td colspan="5">
Опция file представляет собой отбор определённого файла, например, "CUA.csv".
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Нет</td>
<td>Да</td>
<td>Да</td>
<td>Нет</td>
</tr>

<tr>
<td>
--big
</td>
<td colspan="5">
Опция big регулирует отбор, если в ресурсе слишком много объектов, 
то данная опция предотвращает обработку.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
</tr>

<tr>
<td>
--memory-limit
</td>
<td colspan="5">
Опция memory-limit регулирует использование оперативной памяти,
назначает ограничение.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Нет</td>
</tr>

<tr>
<td>
--max-products
</td>
<td colspan="5">
Опция max-products регулирует количество объектов, которое можно создать.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
</tr>

<tr>
<td>
--more-than-one
</td>
<td colspan="5">
Опция more-than-one определяет будет ли выполнен разовый или конвейерный запуск.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Да</td>
<td>Нет</td>
</tr>

<tr>
<td>
--serial-folder
</td>
<td colspan="5">
Опция serial-folder определяет сериализованный каталог объекты которого
будет конвертированы в сущности таблиц.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Нет</td>
<td>Да</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
</tr>

<tr>
<td>
--skip-serials
</td>
<td colspan="5">
Опция skip-serials определяет список каталогов серий которые
будет пропущены в обработке.
</td>
</tr>
<tr>
<td>Наличие</td>
<td>Нет</td>
<td>Да</td>
<td>Нет</td>
<td>Нет</td>
<td>Нет</td>
</tr>

</tbody>
</table>
