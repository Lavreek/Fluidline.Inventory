<?php

namespace App\Command\Helper;

/**
 * @class Directory
 *
 * Класс хранит пути и переменные модуля продукции.
 */
class Directory
{
    /**
     * Путь к модулю продукции
     * Parameter /config/services.yaml: products: '%kernel.project_dir%/public/products/'
     * @var string $products
     */
    private string $products;

    /**
     * Путь к продукции
     * @var string $crawler
     */
    private string $crawler;

    /**
     * Путь к блокировкам продукции
     * @var string $locks
     */
    private string $locks;

    /**
     * Путь к сериализованным файлам продукции
     * @var string $serialize
     */
    private string $serialize;

    /**
     * Путь к файлу логирования обработки
     * @var string $logfile
     */
    private string $logfile;

    /**
     * Путь к файлам цен на продукцию
     * @var string $price
     */
    private string $price;

    /**
     * Путь к файлам моделей продукции
     * @var string $model
     */
    private string $model;

    /**
     * Путь к файлам моделей продукции
     * @var string $image
     */
    private string $image;


    public function __construct() { }


    /**
     * Установить корневой путь к модулю продукции
     * @param string $path
     * @return Directory
     */
    public function setProductsPath(string $path) : Directory
    {
        $this->products = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }

        $this->setSerializePath($path ."serialized/");
        $this->setCrawlerPath($path ."inventory/");
        $this->setLocksPath($path ."locks/");
        $this->setLogfilePath($path ."logs/command.log");
        $this->setPricePath($path ."prices/");
        $this->setModelPath($path ."models/");
        $this->setImagePath($path ."images/");

        return $this;
    }

    /** @deprecated
     *
     * Установить путь к изображениям продукции
     * @param string $path
     * @return void
     */
    public function setImagePath(string $path) : void
    {
        $this->image = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к изображениям продукции
     * @return string
     */
    public function getImagePath() : string
    {
        return $this->image;
    }

    /** @deprecated
     *
     * Установить путь к моделям продукции
     * @param string $path
     * @return void
     */
    public function setModelPath(string $path) : void
    {
        $this->model = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к моделям продукции
     * @return string
     */
    public function getModelPath() : string
    {
        return $this->model;
    }

    /** @deprecated
     *
     * Установить путь к ценам на продукцию
     * @param string $path
     * @return void
     */
    public function setPricePath(string $path) : void
    {
        $this->price = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к ценам на продукцию
     * @return string
     */
    public function getPricePath() : string
    {
        return $this->price;
    }

    /** @deprecated
     *
     * Установить путь к сериализованным данным
     * @param string $path
     * @return void
     */
    public function setSerializePath(string $path) : void
    {
        $this->serialize = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к сериализованным данным
     * @return string
     */
    public function getSerializePath() : string
    {
        return $this->serialize;
    }

    /**
     * Получить корневой путь к модулю продукции
     * @param string $path
     * @return Directory
     */
    public function getProductsPath() : string
    {
        return $this->products;
    }

    /** @deprecated
     *
     * Установить путь к продукции
     * @param string $path
     * @return void
     */
    public function setCrawlerPath(string $path) : void
    {
        $this->crawler = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к продукции
     * @return string
     */
    public function getCrawlerPath() : string
    {
        return $this->crawler;
    }

    /** @deprecated
     *
     * Установить путь к заблокированной продукции
     * @param string $path
     * @return void
     */
    public function setLocksPath(string $path) : void
    {
        $this->locks = $path;

        if (!$this->checkPath($path)) {
            $this->createDirectory($path);
        }
    }

    /**
     * Получить путь к блокировкам продукции
     * @return string
     */
    public function getLocksPath() : string
    {
        return $this->locks;
    }

    /** @deprecated
     *
     * Установить путь к файлу логирования
     * @param string $path
     * @return void
     */
    public function setLogfilePath(string $path) : void
    {
        $this->logfile = $path;

        if (!$this->checkPath(dirname($path))) {
            $this->createDirectory(dirname($path));
        }
    }

    /**
     * Получить путь к файлу логирования
     * @return string
     */
    public function getLogfilePath() : string
    {
        return $this->logfile;
    }

    /**
     * Проверка существования пути
     * @param string $path
     * @return bool
     */
    public function checkPath(string $path) : bool
    {
        if (!is_dir($path)) {
            return false;
        }

        return true;
    }

    /**
     * Создание каталога
     * @param string $path
     * @return void
     */
    public function createDirectory(string $path) : void
    {
        mkdir($path, 0755, recursive: true);
    }
}