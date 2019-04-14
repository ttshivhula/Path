<?php
/**
 * @Author Sulaiman.Adewale
 * @File Router
 * @Date: 10/22/2018
 * @Time: 12:14 AM
 */

namespace Path\Core\Http;

use Path\Core\Http\Request;
use Path\Core\Http\Response;
use Path\Core\Error\Exceptions;
use Path\Core\Router\Route\Controller;


class Router
{
    public  $request;
    public  $root_path;
    private $database;
    private $response_instance;
    private $build_path = "";
    private $controllers_path = "path/Controllers/Route/";
    private $controllers_namespace = "Path\\App\\Controllers\\Route\\";
    private $middleware_path = "path/Http/MiddleWares/";
    private $middleware_namespace = "Path\\App\\Http\\MiddleWare\\";
    private $assigned_paths = [ //to hold all paths assigned

    ];
    private $exception_callback; //Callback exception

    const VALID_REQUESTS = [ //Accepted request type
        "GET",
        "POST",
        "PUT",
        "PATCH",
        "DELETE",
        "COPY",
        "HEAD",
        "OPTIONS",
        "LINK",
        "UNLINK",
        "PURGE",
        "LOCK",
        "PROPFIND",
        "VIEW"
    ];
    public $real_path;

    /**
     * Router constructor.
     * @param string $root_path
     */
    public function __construct($root_path = "/")
    {
        $this->root_path = $root_path;
        $this->request = new Request();
        $this->database = null;
        $this->real_path =  preg_replace(
            "/[^\w:.\/\d\-].*$/m",
            "",
            $this->request->server->REQUEST_URI ?? $this->request->server->REDIRECT_URL
        );

        //        echo $this->real_path;
        //        TODO: Initialize model for database
        $this->response_instance = new Response($this->build_path);
    }

    public function setBuildPath($path)
    {
        $this->build_path = $path;
        $this->response_instance = new Response($this->build_path);
    }

    /**
     * set header from associative array
     * @param array $headers
     */
    private static function set_header(array $headers)
    {
        foreach ($headers as $header => $value) {
            header("{$header}: $value");
        }
    }
    private static function path_matches($param, $raw_param)
    {
        //        echo $param,$raw_param;
        $raw_param = substr($raw_param, 1);
        if (strpos($raw_param, ":") > -1) {
            $type = strtolower(explode(":", $raw_param)[1]);
            switch ($type) {
                case "int":
                    if (!preg_match("/^\d+$/", $param)) {
                        return false;
                    }
                    break;
                case "float":
                    if (!preg_match("/^\d+\.\d+$/", $param)) {
                        return false;
                    }
                    break;
                default:
                    $type = preg_replace("/\}$/", "", preg_replace("/^\{/", "", $type));
                    if (!preg_match("/{$type}/", $param)) { //Check if the regex match the URL parameter
                        return false;
                    }

                    break;
            }
        }
        return true;
    }
    private static function is_root($real_path, $path)
    {
        $b_real_path = array_values(array_filter(explode("/", $real_path), function ($p) {
            return strlen(trim($p)) > 0 && trim($p[0]) != "?";
        })); //get all paths in a array, filter
        $b_path = array_values(array_filter(explode("/", $path), function ($p) {
            return strlen(trim($p)) > 0;
        }));
        //        echo "Comparing: ".$path.PHP_EOL;
        //        var_dump([
        //            "b_real_path" => $b_real_path,
        //            "b_path" => $b_path,
        //        ]);
        //        echo PHP_EOL.PHP_EOL.PHP_EOL;
        if ($real_path == $path)
            return true;

        $matches = 0;
        for ($i = 0; $i < count($b_path); $i++) { //loop through the path template instead of real  path

            $c_path = trim(@$b_path[$i]);
            $c_real_path = trim(@$b_real_path[$i]);
            //            echo $c_real_path,"/",$c_path;
            if ($c_path == $c_real_path) { //if current templ path == browser path, count as matched
                $matches++;
            } elseif ($c_path != $c_real_path && $c_path[0] == "@" && self::path_matches($c_real_path, $c_path)) { //if current path templ not equal to current raw path, and current path templ is is param, and the param obeys the restriction add to match count
                $matches++;
            }
        }
        //        echo PHP_EOL."it was:";
        //        var_dump($matches == count($b_path));
        //        echo var_dump($matches == count($b_path));
        return $matches == count($b_path);
    }
    /**
     * Compare browser path and path template to
     * @param $real_path
     * @param $path
     * @return bool
     */
    public static function compare_path($real_path, $path)
    {

        /*
         * $path holds the path template
         * $real_path holds the path from the browser
         */
        $b_real_path = array_values(array_filter(explode("/", $real_path), function ($p) {
            return strlen(trim($p)) > 0;
        })); //get all paths in a array, filter
        $b_path = array_values(array_filter(explode("/", $path), function ($p) {
            return strlen(trim($p)) > 0;
        }));

        if ($real_path == $path) { //if the path are literally the same, don't do much hard job, return true
            return true;
        }
        //        Else, continue checking

        $matched = 0; //number of matched paths(Both template and path
        for ($i = 0; $i < count($b_real_path); $i++) {
            if ($i > count($b_path)) { //if the amount of url path is more than required, return false
                return false;
            }
            //            Continue execution
            $c_path = @$b_path[$i]; //current path (template)
            $c_real_path = @$b_real_path[$i]; //current path (from web browser)
            if ($c_path == $c_real_path) { // current template path is equal to real path from browser count it as matched
                $matched++; //count
            } elseif ($c_path != $c_real_path && $c_path[0] == "@" && !!$c_path && $c_real_path) { //if path template is not equal to current path, and real path
                $matched++;
            } else {
                return false;
            }
        }
        return $matched == count($b_path);
    }

    private function write_response($response)
    {
        if (!$response instanceof Response)
            throw new Exceptions\Router("Callback function expected to return an instance of Response Class");
        http_response_code($response->status); //set response code
        self::set_header($response->headers); //set header
        if ($response->is_binary) {
            readfile($response->content);
        } else {
            print($response->content);
        }

        @ob_end_flush();
        @ob_flush();
        @flush();
        die();
    }

    /**
     * @param $param
     * @param $raw_param
     * @param $path
     * @return bool
     * @throws Exceptions\Router
     */
    public function type_check($param, $raw_param, $path)
    { //throw exception if specified type doesn't match the dynamic url(url from the browser)
        if (strpos($raw_param, ":") > -1) {
            $type = strtolower(explode(":", $raw_param)[1]);
            switch ($type) {
                case "int":
                    if (!preg_match("/^\d+$/", $param)) {
                        $error = ["msg" => "{$param} is not a {$type} in {$path}", "path" => $path];
                        if (is_callable($this->exception_callback)) {
                            $exception_callback = call_user_func_array($this->exception_callback, [$this->request, $this->response_instance, $error]);
                        } else {
                            $exception_callback = false;
                        }
                        if ($exception_callback) {
                            $this->write_response($exception_callback);
                            return false;
                        } else {
                            throw new Exceptions\Router($error['msg']);
                        }
                    }
                    break;
                case "float":
                    if (!preg_match("/^\d+\.\d+$/", $param)) {
                        $error = ["msg" => "{$param} is not a {$type} in {$path}", "path" => $path];
                        if (is_callable($this->exception_callback)) {
                            $exception_callback = call_user_func_array($this->exception_callback, [$this->request, $this->response_instance, $error]);
                        } else {
                            $exception_callback = false;
                        }
                        if ($exception_callback) {
                            $this->write_response($exception_callback);
                            return false;
                        } else {
                            throw new Exceptions\Router($error['msg']);
                        }
                    }

                    break;
                default:
                    $type = preg_replace("/\}$/", "", preg_replace("/^\{/", "", $type));
                    if (!preg_match("/{$type}/", $param)) { //Check if the regex match the URL parameter
                        $error = ["msg" => "{$param} does not match {$type} Regex in {$path}", "path" => $path];
                        if (is_callable($this->exception_callback)) {
                            $exception_callback = call_user_func_array($this->exception_callback, [$this->request, $this->response_instance, $error]);
                        } else {
                            $exception_callback = false;
                        }
                        if ($exception_callback) {
                            $this->write_response($exception_callback);
                            return false;
                        } else {
                            throw new Exceptions\Router($error['msg']);
                        }
                    }


                    break;
            }
        }
        return true;
    }
    private function concat_path($root, $path)
    {
        return $root . $path;
    }
    public static function get_param_name($raw_param)
    {
        $param = explode(":", $raw_param);
        return $param[0];
    }
    /**
     * @param $real_path
     * @param $path
     * @return bool|object
     */
    public function get_params(
        $real_path,
        $path
    ) {
        $path_str = $path;
        $b_real_path = array_values(array_filter(explode("/", $real_path), function ($p) {
            return strlen(trim($p)) > 0;
        }));
        $b_path = array_values(array_filter(explode("/", $path), function ($p) {
            return strlen(trim($p)) > 0;
        }));;

        $params = [];


        for ($i = 0; $i < count($b_real_path); $i++) {
            if ($i >= count($b_path)) { //if the amount of url path is more than required, return false
                return (object)$params;
            }
            $path = $b_path[$i];
            if (!is_null($b_path[$i]) && @$path[0] == "@" && !is_null($b_real_path[$i])) {
                //                TODO: check for string typing
                $raw_param = $path;
                $param = self::get_param_name(substr($path, 1, strlen($path)));
                if (!self::path_matches($b_real_path[$i], $raw_param)) { //if type check doesn't match don't return any param
                    return false;
                }
                $params[$param] = $b_real_path[$i];
            }
        }
        return (object)$params;
    }

    private function validateAllMiddleWare($middle_wares, $params, $_path, $real_path)
    {
        if (!is_array($middle_wares) || is_string($middle_wares))
            $middle_wares = [$middle_wares];

        $request = new Request();
        $request->params = $params;
        foreach ($middle_wares as $middle_ware) {
            //            echo $middle_ware;
            if ($middle_ware) {
                $middle_ware_name = explode("\\", $middle_ware);
                $middle_ware_name = $middle_ware_name[count($middle_ware_name) - 1];
                //            Load middleware class
                $ini_middleware = new $middle_ware();

                if ($ini_middleware instanceof MiddleWare) {
                    //            initialize middleware



                    //            Check middle ware return
                    $check_middle_ware = $ini_middleware->validate($request, $this->response_instance);
                    //                call the fall_back response
                    $fallback_response = $ini_middleware->fallBack($request, $this->response_instance);
                    if (!$check_middle_ware) { //if the middle ware control method returns false

                        if (!is_null($fallback_response)) { //if user has a fallback method

                            if ($fallback_response && !$fallback_response instanceof Response) {
                                throw new Exceptions\Router(" \"fallBack\" method for \"{$middle_ware_name}\" MiddleWare is expected to return an instance of Response");
                            } else {
                                $this->write_response($fallback_response);
                                return true;
                            }
                        }

                        if ($this->exception_callback) {
                            $exception_callback = call_user_func_array($this->exception_callback, [$request, $this->response_instance, ['error_msg' => "MiddleWare validation failed for \"{$real_path}\""]]);
                            if ($exception_callback instanceof Response) {
                                $this->write_response($exception_callback);
                            }
                        } else {
                            throw new Exceptions\Router("MiddleWare validation failed for \"{$real_path}\"");
                        }
                    }
                } else {
                    throw new Exceptions\Router("Expected \"{$middle_ware->method}\" to implement \"Path\\Http\\MiddleWare\" interface in \"{$_path}\"");
                }
            }
        }
    }

    /**
     * @param $method
     * @param $root
     * @param $path
     * @param $callback
     * @param null $middle_ware
     * @param null $fallback
     * @param bool $is_group
     * @return bool
     * @throws Exceptions\Router
     * @internal param $_path
     */
    private function response(
        $method,
        $root,
        $path,
        $callback,
        $middle_ware = null,
        $fallback = null,
        $is_group = false
    ) {

        if (!$path || is_null($path))
            throw new Exceptions\Router("Specify Path for your router");

        $_path = $this::joinPath($root, $path);
        $real_path = trim($this->real_path);
        $params = $this->get_params($real_path, $_path);
        if (!self::compare_path($real_path, $_path) && !$is_group) { //if the browser path doesn't match specified path template
            return false;
        }


        $this->assigned_paths[$root][] = [
            "path"      => $_path,
            "method"    => $method,
            "is_group"  => $is_group
        ];

        $this->validateAllMiddleWare($middle_ware, $params, $_path, $real_path);

        //        Set the path to list of paths

        //        TODO: check if path contains a parameter path/$id
        //            Check if method calling response is
        if ($is_group) {
            $router = new Router($_path);
            $c = $callback($router); //call the callback, pass the params generated to it to be used
        } else {
            $request = new Request();
            $request->params = $params;
            if (is_string($callback) && strpos($callback, "->")) {
                $_callback = $this->breakController($callback, $params);

                try {
                    $class = $_callback->ini_class->{$_callback->method}($request, $this->response_instance);
                } catch (\Throwable $e) {
                    throw new Exceptions\Router($e->getMessage(), 0, $e);
                }
                if ($class instanceof Response) { //Check if return value from callback is a Response Object
                    $this->write_response($class);
                }
            } else {
                /** ************************************************ */
                //Check if the callback is a Controller instance and call the response method with the $request and $response as parameter
                if (is_string($callback)) {
                    //                    check if the string is used to represent the class and import amd instantiate it
                    $callback = $this->importControllerFromString($callback, $params);
                }

                if ($callback instanceof \Closure)
                    $c = $callback($request, $this->response_instance);
                elseif ($callback instanceof Controller or method_exists($callback, 'response'))
                    $c = $callback->response($request, $this->response_instance);
                else
                    throw new Exceptions\Router('Custom Class Object must have a response method');

                //                $c = ! $callback instanceof \Closure ? $callback->response($request, $this->response_instance)
                //                    : $callback($request,$this->response_instance);

                if ($c instanceof Response) { //Check if return value from callback is a Response Object
                    $this->write_response($c);
                } elseif ($c and !$c instanceof Response) {
                    throw new Exceptions\Router("Expecting an instance of Response to be returned at \"GET\" -> \"$_path\"");
                }
            }
            //call the callback, pass the params generated to it to be used
        }
    }

    /**
     * @return bool
     */
    private function should_fall_back()
    {
        $real_path = trim($this->real_path);
        $current_method = strtoupper($this->request->METHOD);

        foreach ($this->assigned_paths as $root => $paths) {
            $root = $root == "/" ? "" : $root;
            for ($i = 0; $i < count($this->assigned_paths); $i++) {
                //                var_dump($root.$paths[$i]['path']);
                if ($paths[$i]['is_group'] and self::is_root($real_path, $paths[$i]['path'])) {
                    return false;
                }
                if (self::compare_path($real_path, $root . $paths[$i]['path']) && ($paths[$i]['method'] == $current_method || $paths[$i]['method'] == "ANY")) {
                    return false;
                }
            }
        }

        return true;
    }
    static function joinPath($root, $path)
    {
        if ($root != "/") {
            $root = (strripos($root, "/") == (strlen($root) - 1)) ? $root : $root . "/";
        } else {
            $root = "";
        }

        $path = (strripos($path, "/") == 0) ? (substr_replace($path, "", 0, 0)) : $path;
        return $root . $path;
    }
    public function group($path, $callback)
    {

        if (is_array($path)) { //check if path is associative array or a string
            $_path = $path['path'] ?? null;
            $_middle_ware = $path['middleware'] ?? null;
            $_fallback = $path['fallback'] ?? null;
        } else {
            $_path = $path;
            $_middle_ware = null;
            $_fallback  = null;
        }

        $real_path = trim($this->real_path);
        if (self::is_root($real_path, self::joinPath($this->root_path, $_path))) {
            $this->response("ANY", $this->root_path, $_path, $callback, $_middle_ware, $_fallback, true);
            die();
        }
    }
    private function breakController($controller_str, $params)
    {
        //        break string
        if (!preg_match("/([\S]+)\-\>([\S]+)/", $controller_str))
            throw new Exceptions\Router("Invalid Router String");

        //        Break all string to array
        $contr_breakdown = array_values(array_filter(explode("->", $controller_str), function ($m) {
            return strlen(trim($m)) > 0;
        })); //filter empty array
        $class_ini = $contr_breakdown[0];

        if (strpos($class_ini, "\\") === false)
            $class_ini = $this->controllers_namespace . $class_ini;

        //        load_class($class_ini,"controllers");

        try {
            $request = new Request();
            $request->params = $params;
            $class_ini = new $class_ini($request, $this->response_instance);
        } catch (\Throwable $e) {
            throw new Exceptions\Router($e->getMessage());
        }

        return (object)["ini_class" => $class_ini, "method" => $contr_breakdown[1]];
    }

    private function importControllerFromString($controller, $params)
    {

        $request = new Request();
        $request->params = $params;

        $controller_instance = new $controller($request, $this->response_instance);

        return $controller_instance;
    }
    private function processMultipleRequestPath($path, $callback, $method)
    {

        if (is_array($path)) { //check if path is associative array or a string
            $_path = $path['path'] ?? null;
            $_middle_ware = $path['middleware'] ?? null;
            $_fallback = $path['fallback'] ?? null;
        } else {
            $_path = $path;
            $_middle_ware = null;
            $_fallback = null;
        }
        if (is_string($_path)) {
            $_path = array_filter(explode("|", $_path), function ($path) {
                return strlen(trim($path)) > 0;
            });
        }
        if (is_null($_path))
            return;
        foreach ($_path as $each_path) {
            $this->processRequest(["path" => trim($each_path), "middleware" => $_middle_ware, "fallback" => $_fallback], $callback, $method);
        }
    }
    private function processRequest($path, $callback, $method)
    {
        //        echo "<pre>";
        //        var_dump($path);
        if (is_array($path)) { //check if path is associative array or a string
            $_path = $path['path'] ?? null;
            $_middle_ware = $path['middleware'] ?? null;
            $_fallback = $path['fallback'] ?? null;
        } else {
            $_path = $path;
            $_middle_ware = null;
            $_fallback    = null;
        }
        $real_path = trim($this->real_path);
        //Check if path is the one actively visited in browser
        if ((strtoupper($this->request->METHOD) == $method || $method == "ANY") && self::compare_path($real_path, self::joinPath($this->root_path, $_path))) {

            //            Check if $callback is a string, parse appropriate
            $this->response($method, $this->root_path, $_path, $callback, $_middle_ware, $_fallback);
        }
    }

    /**
     * @param $path
     * @param $callback
     * @return $this
     * @throws Exceptions\Router
     */
    public function get($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "GET");
        return $this;
    }
    public function any($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "ANY");
        return $this;
    }
    public function post($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "POST");
        return $this;
    }
    public function put($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "PUT");
        return $this;
    }
    public function patch($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "PATCH");
        return $this;
    }
    public function delete($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "DELETE");
        return $this;
    }
    public function copy($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "COPY");
        return $this;
    }
    public function head($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "HEAD");
        return $this;
    }
    public function options($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "HEAD");
        return $this;
    }
    public function link($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "LINK");
        return $this;
    }
    public function unlink($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "UNLINK");
        return $this;
    }
    public function purge($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "PURGE");
        return $this;
    }
    public function lock($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "LOCK");
        return $this;
    }
    public function propFind($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "PROPFIND");
        return $this;
    }
    public function view($path, $callback)
    {
        $this->processMultipleRequestPath($path, $callback, "VIEW");
        return $this;
    }

    public function exceptionCatch($callback)
    {
        if (!is_callable($callback))
            throw new Exceptions\Router("ExceptionCatch expects a callable function");

        $this->exception_callback = $callback;
        return true;
    }
    public function error404($callback)
    { //executes when no route is specified
        if ($this->should_fall_back()) { //check if the current request doesn't match any request
            //            print_r($this->assigned_paths);
            $c = $callback($this->request, $this->response_instance); //call the callback, pass the params generated to it to be used
            if ($c instanceof Response) { //Check if return value from callback is a Response Object

                $this->write_response($c);
            } elseif ($c and !$c instanceof Response) {
                throw new Exceptions\Router("Expecting an instance of Response to be returned at \"Fallback\"");
            }
        }
    }
}
