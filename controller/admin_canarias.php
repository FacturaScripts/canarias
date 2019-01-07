<?php
/**
 * This file is part of canarias plugin for FacturaScripts
 * Copyright (C) 2016       Joe Nilson                <joenilson@gmail.com>
 * Copyright (C) 2017       Francesc Pineda Segarra  <francesc.pineda@x-netdigital.com>
 * Copyright (C) 2016-2019  Carlos García Gómez      <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of admin_canarias
 *
 * @author Francesc Pineda Segarra
 * @author Carlos García Gómez
 */
class admin_canarias extends fs_controller
{

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Canarias', 'admin');
        $this->share_extensions();
    }

    protected function private_core()
    {
        $opcion = isset($_GET['opcion']) ? $_GET['opcion'] : '';
        switch ($opcion) {
            case 'moneda':
                $this->empresa->coddivisa = 'EUR';
                if ($this->empresa->save()) {
                    $this->new_message('Datos guardados correctamente.');
                }
                break;

            case 'pais':
                $this->empresa->codpais = 'ESP';
                if ($this->empresa->save()) {
                    $this->new_message('Datos guardados correctamente.');
                }
                break;

            case 'regimenes':
                $fsvar = new fs_var();
                if ($fsvar->simple_save('cliente::regimenes_iva', 'Simplificado,Común,Exento')) {
                    $this->new_message('Datos guardados correctamente.');
                }
                break;

            case 'impuestos':
                $this->set_impuestos();
                break;

            case 'actualizar_config':
                $this->actualizar_config2();
                break;

            default:
                $this->check_menu();
                $this->check_ejercicio();
        }
    }

    private function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'impuestos_canarias';
        $fsext->from = __CLASS__;
        $fsext->to = 'contabilidad_ejercicio';
        $fsext->type = 'fuente';
        $fsext->text = 'Impuestos Canarias';
        $fsext->params = 'plugins/canarias/extras/canarias.xml';
        $fsext->save();
    }

    private function check_menu()
    {
        // Limpiamos la cache por si ha habido cambio en la estructura de las tablas
        $this->cache->clean();

        if (file_exists(__DIR__)) {
            /// activamos las páginas del plugin
            foreach (scandir(__DIR__) as $f) {
                if ($f != '.' && $f != '..' && is_string($f) && strlen($f) > 4 && !is_dir($f) && $f != __CLASS__ . '.php') {
                    $page_name = substr($f, 0, -4);

                    require_once __DIR__ . '/' . $f;
                    $new_fsc = new $page_name();

                    if (!$new_fsc->page->save()) {
                        $this->new_error_msg("Imposible guardar la página " . $page_name);
                    }

                    unset($new_fsc);
                }
            }
        } else {
            $this->new_error_msg('No se encuentra el directorio ' . __DIR__);
        }

        $this->load_menu(TRUE);
    }

    private function check_ejercicio()
    {
        $ej0 = new ejercicio();
        foreach ($ej0->all_abiertos() as $ejercicio) {
            if ($ejercicio->longsubcuenta != 10) {
                $ejercicio->longsubcuenta = 10;
                if ($ejercicio->save()) {
                    $this->new_message('Datos del ejercicio ' . $ejercicio->codejercicio . ' modificados correctamente.');
                } else {
                    $this->new_error_msg('Error al modificar el ejercicio.');
                }
            }
        }
    }

    public function regimenes_ok()
    {
        $fsvar = new fs_var();
        $regimenes = $fsvar->simple_get('cliente::regimenes_iva');
        return $regimenes == 'Simplificado,Común,Exento';
    }

    public function ejercicio_ok()
    {
        $ej0 = new ejercicio();
        $ejerccio = $ej0->get_by_fecha($this->today());
        if ($ejerccio) {
            $subc0 = new subcuenta();
            foreach ($subc0->all_from_ejercicio($ejerccio->codejercicio) as $sc) {
                return true;
            }
        }

        return false;
    }

    public function impuestos_ok()
    {
        $imp0 = new impuesto();
        foreach ($imp0->all() as $i) {
            if ($i->codimpuesto == 'IGIC7') {
                return true;
            }
            
            if ($i->codimpuesto == 'IGIC6.5') {
                return true;
            }
        }

        return false;
    }

    private function set_impuestos()
    {
        /// eliminamos los impuestos que ya existen (los de España)
        $imp0 = new impuesto();
        foreach ($imp0->all() as $impuesto) {
            $this->desvincular_articulos($impuesto->codimpuesto);
            $impuesto->delete();
        }

        /// añadimos los de Canarias
        $codimp = array("IGIC6.5", "IGIC3", "IGIC0");
        $desc = array("IGIC 6.5%", "IGIC 3%", "IGIC 0%");
        $recargo = 0;
        $iva = array(6.5, 3, 0);
        $cant = count($codimp);
        for ($i = 0; $i < $cant; $i++) {
            $impuesto = new impuesto();
            $impuesto->codimpuesto = $codimp[$i];
            $impuesto->descripcion = $desc[$i];
            $impuesto->recargo = $recargo;
            $impuesto->iva = $iva[$i];
            $impuesto->save();
        }

        $this->impuestos_ok = TRUE;
        $this->new_message('Impuestos de Canarias añadidos.');
    }

    private function desvincular_articulos($codimpuesto)
    {
        $sql = "UPDATE articulos SET codimpuesto = null WHERE codimpuesto = "
            . $this->empresa->var2str($codimpuesto) . ';';

        if ($this->db->table_exists('articulos')) {
            $this->db->exec($sql);
        }
    }

    public function formato_divisa_ok()
    {
        return FS_POS_DIVISA == 'right';
    }

    public function nombre_impuesto_ok()
    {
        return $GLOBALS['config2']['iva'] == 'IGIC';
    }

    public function actualizar_config2()
    {
        //Configuramos la información básica para config2.ini
        $guardar = FALSE;
        $config2 = array();
        
        /* No hace falta indicarlas todas, sólo las diferentes */
        $config2['zona_horaria'] = "Atlantic/Canary";
        $config2['iva'] = "IGIC";
        foreach ($GLOBALS['config2'] as $i => $value) {
            if (isset($config2[$i])) {
                $GLOBALS['config2'][$i] = htmlspecialchars($config2[$i]);
                $guardar = TRUE;
            }
        }

        if ($guardar) {
            $file = fopen('tmp/' . FS_TMP_NAME . 'config2.ini', 'w');
            if ($file) {
                foreach ($GLOBALS['config2'] as $i => $value) {
                    if (is_numeric($value)) {
                        fwrite($file, $i . " = " . $value . ";\n");
                    } else {
                        fwrite($file, $i . " = '" . $value . "';\n");
                    }
                }
                fclose($file);
            }
            $this->new_message('Datos de configuracion regional guardados correctamente.');
        }
    }
}
