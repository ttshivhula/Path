<?php
/*
* This FIle was automatically generated By Path
* Modify to your advantage
*/

namespace Path\Database\Migration;


use Path\Database\Model;
use Path\Database\Prototype;
use Path\Database\Structure;
use Path\Database\Table;

import(
    "core/classes/Database/Prototype",
    "core/classes/Database/Table",
    "core/classes/Database/Structure"
);

class Services implements Table
{
    public $table_name = "services";
    public $primary_key = "id";
    public function install(Structure &$services)
    {

        $services->column("name")
            ->nullable()
            ->type("text");

        $services->column("description")
            ->type("text")
            ->nullable();

        $services->column("is_selected")
            ->type("boolean")
            ->default(0);

    }

    public function uninstall()
    {
    }

    public function populate(Model $table)
    {
    }

    public function update(Structure &$user)
    {
    }
}