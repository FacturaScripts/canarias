<?php
/**
 * This file is part of canarias plugin for FacturaScripts
 * Copyright (C) 2015-2019 Carlos García Gómez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of linea_libro_iva
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class linea_libro_iva extends fs_model
{

    public $id;
    public $idasiento;
    public $idpartida;
    public $codejercicio;
    public $tipo;
    public $fecha;
    public $numero;
    public $codserie;
    public $cifnif;
    public $nombre;
    public $importe;
    public $baseimponible;
    public $iva;
    public $totaliva;

    public function __construct($l = FALSE)
    {
        new partida();
        parent::__construct('lineaslibroiva');
        if ($l) {
            $this->id = $this->intval($l['id']);
            $this->idasiento = $this->intval($l['idasiento']);
            $this->idpartida = $this->intval($l['idpartida']);
            $this->codejercicio = $l['codejercicio'];
            $this->tipo = $l['tipo'];
            $this->fecha = date('d-m-Y', strtotime($l['fecha']));
            $this->numero = $this->intval($l['numero']);
            $this->codserie = $l['codserie'];
            $this->cifnif = $l['cifnif'];
            $this->nombre = $l['nombre'];
            $this->importe = floatval($l['importe']);
            $this->baseimponible = floatval($l['baseimponible']);
            $this->iva = floatval($l['iva']);
            $this->totaliva = floatval($l['totaliva']);
        } else {
            $this->id = NULL;
            $this->idasiento = NULL;
            $this->idpartida = NULL;
            $this->codejercicio = NULL;
            $this->tipo = NULL;
            $this->fecha = date('d-m-Y');
            $this->numero = NULL;
            $this->codserie = NULL;
            $this->cifnif = '';
            $this->nombre = '';
            $this->importe = 0;
            $this->baseimponible = 0;
            $this->iva = 0;
            $this->totaliva = 0;
        }
    }

    protected function install()
    {
        return '';
    }

    public function get_by_idpartida($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idpartida = " . $this->var2str($id) . ";");
        if ($data) {
            return new linea_libro_iva($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
        }
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET idasiento = " . $this->var2str($this->idasiento) .
                ", idpartida = " . $this->var2str($this->idpartida) .
                ", codejercicio = " . $this->var2str($this->codejercicio) .
                ", tipo = " . $this->var2str($this->tipo) .
                ", fecha = " . $this->var2str($this->fecha) .
                ", numero = " . $this->var2str($this->numero) .
                ", codserie = " . $this->var2str($this->codserie) .
                ", cifnif = " . $this->var2str($this->cifnif) .
                ", nombre = " . $this->var2str($this->nombre) .
                ", importe = " . $this->var2str($this->importe) .
                ", baseimponible = " . $this->var2str($this->baseimponible) .
                ", iva = " . $this->var2str($this->iva) .
                ", totaliva = " . $this->var2str($this->totaliva) .
                "  WHERE id = " . $this->var2str($this->id) . ";";

            return $this->db->exec($sql);
        } else {
            $sql = "INSERT INTO " . $this->table_name . " (idasiento,idpartida,codejercicio,tipo,fecha,numero,"
                . "codserie,cifnif,nombre,importe,baseimponible,iva,totaliva) VALUES " .
                "(" . $this->var2str($this->idasiento) .
                "," . $this->var2str($this->idpartida) .
                "," . $this->var2str($this->codejercicio) .
                "," . $this->var2str($this->tipo) .
                "," . $this->var2str($this->fecha) .
                "," . $this->var2str($this->numero) .
                "," . $this->var2str($this->codserie) .
                "," . $this->var2str($this->cifnif) .
                "," . $this->var2str($this->nombre) .
                "," . $this->var2str($this->importe) .
                "," . $this->var2str($this->baseimponible) .
                "," . $this->var2str($this->iva) .
                "," . $this->var2str($this->totaliva) . ");";

            if ($this->db->exec($sql)) {
                $this->id = $this->db->lastval();
                return TRUE;
            } else
                return FALSE;
        }
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function all($offset = 0)
    {
        $llist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY codejercicio ASC, numero ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        if ($data) {
            foreach ($data as $d) {
                $llist[] = new linea_libro_iva($d);
            }
        }

        return $llist;
    }

    public function all_codejercicios()
    {
        $codlist = array();
        $sql = "SELECT DISTINCT codejercicio FROM " . $this->table_name . " ORDER BY codejercicio ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $codlist[] = $d['codejercicio'];
            }
        }

        return $codlist;
    }

    public function search($codeje, $tipo, $desde, $hasta)
    {
        $llist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codeje)
            . " AND tipo = " . $this->var2str($tipo)
            . " AND fecha >= " . $this->var2str($desde)
            . " AND fecha <= " . $this->var2str($hasta)
            . " ORDER BY fecha ASC, numero ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $llist[] = new linea_libro_iva($d);
            }
        }

        return $llist;
    }
}
