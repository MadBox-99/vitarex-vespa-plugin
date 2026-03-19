<?php

    class VESPA_Datalist {
        protected $source            = '';
        protected $tablename         = '';
        protected $id_field          = '';
        protected $default_order_by  = '';
        protected $default_order_dir = '';
        protected $soft_delete       = false;
        protected $columns           = array();

        public function __construct(){
            add_action( 'wp_ajax_list_' . $this->source . '_items', array($this, 'get_list') );
            add_action( 'wp_ajax_save_' . $this->source , array($this, 'save') );
            add_action( 'wp_ajax_delete_' . $this->source , array($this, 'delete') );
        }

        public function save(){

        }

        public function joins(){
            return '';
        }

        public function checkDelete( $id ){
            return current_user_can( 'manage_options' );
        }

        public function delete(){
            global $wpdb;

            check_ajax_referer( 'vespa_nonce', 'nonce' );

            $id = intval( $_POST['id'] );

            if( ! $this->checkDelete( $id ) ){
                wp_send_json( array("success" => false, "message" => "Jogosulatlan hozzáférés" ) );
            }

            $record = $this->load( $id );

            if( $this->soft_delete === false ){
                $success = $wpdb->delete(
                    $this->tablename,
                    array( $this->id_field => $id ),
                    array( '%d' )
                );
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'is_deleted'    => 1,
                    'deleted_at'    => date('Y-m-d H:i:s'),
                ), array(
                    $this->id_field => $id,
                ),
                array(
                    '%d',
                    '%s',
                ));
            }

            wp_send_json( array("success" => ( $success == 1 ? true : false) ) );
        }

        public function getFilters(){
            if( $this->soft_delete ){
                return $this->tablename . ".is_deleted=0";
            }

            return '1';
        }

        public function addActionButtons( $item ){
            return '-';
        }

        public function load( $id ){
            global $wpdb;

            $record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $this->tablename . " WHERE " . $this->id_field . "= %d", intval($id) ) );

            return $record;
        }

        public function formatColumn( $colname, $colvalue, $item ){
            return $colvalue;
        }

        public function getDeletedList(){
            global $wpdb;

            $filters = $this->tablename . ".is_deleted=1";

            $sql  = "SELECT * FROM " . $this->tablename;
            $sql .= ' ' . $this->joins();
            $sql .= " WHERE " . $filters;

            $rows = $wpdb->get_results( $sql );

            return $rows;
        }

        public function get_list(){
            global $wpdb;

            $filters = $this->getFilters();
            $offset  = 0;
            $length  = 10;

            if( isset($_REQUEST['start']) && is_numeric($_REQUEST['start']) ){
                $offset = intval($_REQUEST['start']);
            }

            if( isset($_REQUEST['length']) && is_numeric($_REQUEST['length']) ){
                $length = intval($_REQUEST['length']);
            }

            // order[0][column], order[0][dir]
            if( isset( $_REQUEST['order'][0]['column'] ) && isset( $this->columns[ $_REQUEST['order'][0]['column'] ] ) ){
                $this->default_order_by = $this->columns[ $_REQUEST['order'][0]['column'] ];
            }

            $allowed_dirs = array('ASC', 'DESC', 'asc', 'desc');
            if( isset($_REQUEST['order'][0]['dir']) && in_array($_REQUEST['order'][0]['dir'], $allowed_dirs) ){
                $this->default_order_dir = $_REQUEST['order'][0]['dir'];
            }

            $response = array(
                "draw" => intval($_REQUEST['draw']),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => array()
            );

            // get number of all records ------------
            $sql  = "SELECT " . $this->id_field . " FROM " . $this->tablename;
            $sql .= ' ' . $this->joins();
            $sql .= " WHERE " . $filters;
            $rows = $wpdb->get_results( $sql );
            $response['recordsTotal'] = $wpdb->num_rows;
            $response['recordsFiltered'] = $wpdb->num_rows;


            // get current page's records ------------
            $sql  = "SELECT * FROM " . $this->tablename;
            $sql .= ' ' . $this->joins();
            $sql .= " WHERE " . $filters;
            $sql .= " ORDER BY " . $this->default_order_by . " " . $this->default_order_dir;
            $sql .= $wpdb->prepare( " LIMIT %d, %d", $offset, $length );

            $rows = $wpdb->get_results( $sql );

            foreach( $rows as $item ){
                $tmp = array();

                foreach( $this->columns as $column ){
                    if( isset($item->{$column}) ){
                        $tmp[] = $this->formatColumn( $column, $item->{$column}, $item );
                    }
                    else {
                        $tmp[] = '';
                    }
                }

                // add an action column
                $tmp[] = $this->addActionButtons( $item );

                $response['data'][] = $tmp;
            }

            wp_send_json( $response );
        }

    }