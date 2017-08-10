<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016 Joe Nilson                joenilson@gmail.com
 * Copyright (C) 2017  Francesc Pineda Segarra  francesc.pineda@x-netdigital.com
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

require_model('ejercicio.php');
require_model('impuesto.php');
require_model('subcuenta.php');

/**
 * Description of admin_canarias
 *
 * @author Francesc Pineda Segarra
 */
class admin_canarias extends fs_controller {

    public function __construct() {
        parent::__construct(__CLASS__, 'Canarias', 'admin');
        $this->share_extensions();
    }

    protected function private_core() {
        $this->checkNewInstallation();
        
        if (isset($_GET['opcion'])) {
            if ($_GET['opcion'] == 'moneda') {
                $this->empresa->coddivisa = 'EUR';
                if ($this->empresa->save()) {
                    $this->new_message('Datos guardados correctamente.');
                }
            } elseif ($_GET['opcion'] == 'pais') {
                $this->empresa->codpais = 'ESP';
                if ($this->empresa->save()) {
                    $this->new_message('Datos guardados correctamente.');
                }
            } elseif ($_GET['opcion'] == 'regimenes') {
                $fsvar = new fs_var();
                if ($fsvar->simple_save('cliente::regimenes_iva', 'Simplificado,Común,Exento')) {
                    $this->new_message('Datos guardados correctamente.');
                }
            } elseif ($_GET['opcion'] == 'impuestos') {
                $this->set_impuestos();
            } elseif ($_GET['opcion'] == 'actualizar_config') {
                $this->actualizar_config2();
            } elseif ($_GET['opcion'] == 'chat_soporte') {
                if ($_GET['status'] == 'disable') {
                    $this->desactivarJsChat();
                } else {
                    $this->activarJsChat();
                }
            }
        } else {
            $this->check_menu();
            $this->check_ejercicio();
        }
    }

    private function share_extensions() {
        $fsext = new fs_extension();
        $fsext->name = 'impuestos_canarias';
        $fsext->from = __CLASS__;
        $fsext->to = 'contabilidad_ejercicio';
        $fsext->type = 'fuente';
        $fsext->text = 'Impuestos Canarias';
        $fsext->params = 'plugins/canarias/extras/canarias.xml';
        $fsext->save();
    }

    private function check_menu() {

        // Limpiamos la cache por si ha habido cambio en la estructura de las tablas
        $this->cache->clean();

        if (file_exists(__DIR__)) {
            /// activamos las páginas del plugin
            foreach (scandir(__DIR__) as $f) {
                if ($f != '.' AND $f != '..' AND is_string($f) AND strlen($f) > 4 AND ! is_dir($f) AND $f != __CLASS__ . '.php') {
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

    private function check_ejercicio() {
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

    public function regimenes_ok() {
        $fsvar = new fs_var();
        $regimenes = $fsvar->simple_get('cliente::regimenes_iva');

        if ($regimenes == 'Simplificado,Común,Exento') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function ejercicio_ok() {
        $ok = FALSE;

        $ej0 = new ejercicio();
        $ejerccio = $ej0->get_by_fecha($this->today());
        if ($ejerccio) {
            $subc0 = new subcuenta();
            foreach ($subc0->all_from_ejercicio($ejerccio->codejercicio) as $sc) {
                $ok = TRUE;
                break;
            }
        }

        return $ok;
    }

    public function impuestos_ok() {
        $ok = FALSE;

        $imp0 = new impuesto();
        foreach ($imp0->all() as $i) {
            if ($i->codimpuesto == 'IGIC7') {
                $ok = TRUE;
                break;
            }
        }

        return $ok;
    }

    private function set_impuestos() {
        /// eliminamos los impuestos que ya existen (los de España)
        $imp0 = new impuesto();
        foreach ($imp0->all() as $impuesto) {
            $this->desvincular_articulos($impuesto->codimpuesto);
            $impuesto->delete();
        }

        /// añadimos los de Canarias
        $codimp = array("IGIC7", "IGIC3", "IGIC0");
        $desc = array("IGIC 7%", "IGIC 3%", "IGIC 0%");
        $recargo = 0;
        $iva = array(7, 3, 0);
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

    private function desvincular_articulos($codimpuesto) {
        $sql = "UPDATE articulos SET codimpuesto = null WHERE codimpuesto = "
                . $this->empresa->var2str($codimpuesto) . ';';

        if ($this->db->table_exists('articulos')) {
            $this->db->exec($sql);
        }
    }

    public function formato_divisa_ok() {
        if (FS_POS_DIVISA == 'right') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function nombre_impuesto_ok() {
        if ($GLOBALS['config2']['iva'] == 'IGIC') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function chat_soporte_ok() {
        $fsvar = new fs_var();
        $chat_soporte_xnet = (bool) $fsvar->simple_get('chat_soporte_xnet');

        if ($chat_soporte_xnet) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function actualizar_config2() {
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

    /**
     * Activa el chat de soporte en todas las páginas como extensión
     */
    private function activarJsChat() {
        $items = '<script type="text/javascript" src="plugins/canarias/view/js/chat_soporte.js"></script>';

        $extensions = array(
            array(
                'name' => 'chat_soporte_xnet',
                'page_from' => __CLASS__,
                'page_to' => NULL,
                'type' => 'head',
                'text' => $items,
                'params' => ''
            ),
        );
        foreach ($extensions as $ext) {
            $fsext = new fs_extension($ext);
            $fsext->save();
        }
        
        $fsvar = new fs_var();
        $fsvar->simple_save('chat_soporte_xnet', TRUE);
        $this->new_message('Chat de soporte activado.');
    }
    
    /**
     * Desactiva el chat de soporte de todas las páginas (requiere cambiar de página para que desaparezca)
     */
    private function desactivarJsChat() {
        $fsext = new fs_extension();
        $fsext = $fsext->get('chat_soporte_xnet', __CLASS__);
        $fsext->delete();
        
        $fsvar = new fs_var();
        $fsvar->simple_delete('chat_soporte_xnet');
        $this->new_message('Chat de soporte desactivado. <a href="'.$this->url().'">Recargar para comprobar que ya no está el chat</a>.');
    }
    
    /**
     * Comprueba si es una instalación nueva, y si lo es, se pre-activa el chat de soporte
     */
    private function checkNewInstallation()
    {
        $fsvar = new fs_var();
        $fsvar = $fsvar->simple_get('canarias_instalado');
        if(!$fsvar) {
            $fsvar = new fs_var();
            $fsvar->simple_save('canarias_instalado', TRUE);
            $this->activarJsChat();
        }
    }
}
