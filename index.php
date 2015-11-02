<?php
class Imgur {
    public $url;
    public $type;
    public $json_response;
    private $api_schema = 'http://api.imgur.com/2/';

    public function __construct( $url ) {

        if ( $this->is_imgur( $url ) ) {
            $this->url = $this->clean_url( $url );
        } else {
            throw new Exception( 'Not Imgur' );
        }

        if( $this->is_album( $this->url ) ) {
            $this->type = 'album';
        } else {
            $this->type = 'image';
        }

        $this->json_response = $this->get_json();
    }

    /**
     * Checks if the given link comes from imgur.
     *
     * It'll check if the url has http in it; this'll minimize the input errors,
     * Then it parses the url and determines with strcmp if the host is imgur.
     *
     * @param  string $url
     * @return boolean
     */
    private function is_imgur( $url ) {
        $url = parse_url( $this->add_http( $url ) , PHP_URL_HOST );

        if ( 'imgur.com' == $url || 'www.imgur.com' == $url ||
            'i.imgur.com' == $url || 'www.i.imgur.com' == $url ||
            'www.m.imgur.com' == $url || 'm.imgur.com' == $url ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the given url has the protocol prefix, if not it'll add it.
     *
     * @param  string $url
     * @return string
     */
    private function add_http( $url ) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url) && !empty( $url ) ) {
            $url = "http://" . $url;
        }
        return strval( $url );
    }

    /**
     * Checks if the given url is an imgur album.
     *
     * @param string $url
     * @return boolean
     */
    private function is_album( $url ) {
        $imgur_id         = $this->get_last_path_segment( $this->clean_url( $url ) );
        $constructed_call = $this->api_schema . 'album/' . $imgur_id . '.json';
        $json             = $this->get_data( $constructed_call );
        $obj              = json_decode( $json );

        if ( ! empty( $obj->album ) ) {
            return true;
        } else {
            return false;
        }
    }

    private function get_json() {
        if ( 'album' == $this->type ) {
            $imgur_id         = $this->get_last_path_segment( $this->clean_url( $this->url ) );
            $constructed_call = $this->api_schema . 'album/' . $imgur_id . '.json';
            $json             = $this->get_data( $constructed_call );
            $obj              = json_decode( $json );
        } else {
            $imgur_id         = $this->get_last_path_segment( $this->clean_url( $this->url ) );
            $constructed_call = $this->api_schema . 'image/' . $imgur_id . '.json';
            $json             = $this->get_data( $constructed_call );
            $obj              = json_decode( $json );
        }

        return $obj;
    }

    private function clean_url( $url ) {
        $url_trimmed       = rtrim( $url, '/' );
        $url_dehashed      = explode( '#' ,$url_trimmed );
        $url_dehashed      = $url_dehashed[0];
        $url_deparametered = explode( '?', $url_dehashed );
        $url_deparametered = $url_deparametered[0];
        $last_segment      = $this->get_last_path_segment( $url_deparametered );
        $path_info         = pathinfo( $last_segment );

        if ( ! empty( $path_info["extension"] ) )  {
            $extension    = $path_info["extension"];
            $clean_url    = str_replace( '.' . $extension, '', $url_deparametered );
            return $clean_url;
        } else {
            return $url_deparametered;
        }

    }

    /**
     * Gets the last segment of any imgur url, usually the imgur unique ID.
     *
     * @param string $url
     * @return string
     */
    private function get_last_path_segment( $url ) {
        $url_trimmed = rtrim( $url, '/' ); // trims trailing slashes
        $url_path    = parse_url( $url, PHP_URL_PATH ); // gets the url path
        $parts       = explode( '/', $url_path );
        $last        = end( $parts );

        return $last;
    }

    /*
     * Downloads with cURL any site and retrieves to var.
     */
    private function get_data( $url ) {
        $ch      = curl_init();
        $timeout = 5;

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );

        $data = curl_exec( $ch );
        curl_close( $ch );

        return $data;
    }

    /*
     * Follows the link at $this->url and downloads it with the imgur name.
     */
    public function download() {
        if ( 'image' == $this->type ) {
            $filename       = parse_url( $this->json_response->image->links->original, PHP_URL_PATH );
            $filename_clean = trim( $filename, '/' );
            copy( $this->json_response->image->links->original, $filename_clean );
        } else {
            $image_array = $this->json_response->album->images;
            foreach ( $image_array as $image ) {
                $filename       = parse_url( $image->links->original, PHP_URL_PATH );
                $filename_clean = trim( $filename, '/' );
                copy( $image->links->original, $filename_clean );
            }
        }
    }

}

try {
    $current_user = posix_getpwuid( posix_geteuid() );
    chdir( $current_user['dir'] . '/Desktop');
    $imgur = new Imgur("{query}");
    $imgur->download();
} catch( Exception $e ) {
    printf( $e );
}
