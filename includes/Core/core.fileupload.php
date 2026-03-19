<?php
    class VespaFileUpload {
        private $name          = '';  
        private $upload_path   = '';  
        private $allowed_types = array('image/jpg', 'application/pdf', 'image/jpeg', 'image/png');

        public function __construct(){
            $this->upload_path = wp_upload_dir();
        }

        public function set_name($name){
            $this->name = $name;
        }

        public function set_allowed_types($types){
            $this->allowed_types = $types;
        }

        public function handle_uploads( $entityid ){
            global $wpdb;

            if( empty($this->name) || empty($this->allowed_types) ){
                return false;
            }

            if( isset($_FILES[ $this->name ]) && $_FILES[ $this->name ]['error'] == UPLOAD_ERR_OK ){
                if( in_array($_FILES[ $this->name ]['type'], $this->allowed_types) ){
                    $upldir   = $this->upload_path['basedir'] . '/files/contest/' . $entityid;
                    $filename = '';
    
                    if( ! file_exists($upldir) ){
                        @mkdir( $upldir, 0777, true );
                    }

                    $success = $wpdb->insert( 'vespa_files', array(
                            'original_name' => $_FILES[ $this->name ]['name'],
                            'hashed_name' => '',
                            'entity' => 'contest',
                            'entity_id' => $entityid,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                                
                        ), array(
                            '%s'
                        ));                    

                    if( $success ){
                        $file_id = $wpdb->insert_id;
                        $filename = md5($file_id);

                        $ext = pathinfo($_FILES[ $this->name ]['name'], PATHINFO_EXTENSION);
                        $filename .= '.' . $ext;

                        $success = $wpdb->update('vespa_files',
                            array("hashed_name" => $filename ),
                            array("file_id"     => $file_id ),
                            array('%s')
                        );                        
                    }
                    else {
                        return false;
                    }
    
                    // filename, dest
                    $s = move_uploaded_file( $_FILES[ $this->name ]['tmp_name'], $upldir . '/' . $filename );
                    if( $s === FALSE ){
                        rename($_FILES[ $this->name ]['tmp_name'], $upldir . '/' . $filename );
                    }

                    return $file_id;
                }

                else {
                    return false;
                }
    
            }
            else {
                return false;
            }
        }
    }