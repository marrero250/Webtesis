<?php
require_once 'data/MysqlManager.php';

/**
 * Controlador del recurso "/affiliates"
 */
class affiliates {

    public static function get($urlSegments) {
    }

    public static function post($urlSegments) {
        if (!isset($urlSegments[0])) {
            throw new ApiException(
                400,
                0,
                "El recurso está mal referenciado",
                "http://localhost",
                "El recurso $_SERVER[REQUEST_URI] no esta sujeto a resultados"
            );
        }

        switch ($urlSegments[0]) {
            case "register":
                return self::saveAffiliate();
                break;
            case "login":
                return self::authAffiliate();
                break;
            case "update":
                return self::updateAffiliate();
                break;
            default:
                throw new ApiException(
                    404,
                    0,
                    "El recurso al que intentas acceder no existe",
                    "http://localhost", "No se encontró el segmento \"affiliates/$urlSegments[0]\".");
        }
    }

    public static function put($urlSegments) {

    }

    public static function delete($urlSegments) {

    }

    private static function saveAffiliate() {
        // Obtener parámetros de la petición
        $parameters = file_get_contents('php://input');
        $decodedParameters = json_decode($parameters, true);

        // Controlar posible error de parsing JSON
        if (json_last_error() != JSON_ERROR_NONE) {
            $internalServerError = new ApiException(
                500,
                0,
                "Error interno en el servidor. Contacte al administrador",
                "http://localhost",
                "Error de parsing JSON. Causa: " . json_last_error_msg());
            throw $internalServerError;
        }

        // Verificar integridad de datos
        // TODO: Implementar restricciones de datos adicionales
        if (!isset($decodedParameters["id"]) ||
            !isset($decodedParameters["password"]) ||
            !isset($decodedParameters["name"]) ||
            !isset($decodedParameters["numero_cuenta"]) ||
            !isset($decodedParameters["telefono"]) ||
            !isset($decodedParameters["address"]) ||
            !isset($decodedParameters["gender"])
        ) {
            // TODO: Crear una excepción individual por cada causa anómala
            throw new ApiException(
                400,
                0,
                "Verifique los datos del afiliado tengan formato correcto",
                "http://localhost",
                "Uno de los atributos del afiliado no está definido en los parámetros");
        }

        // Insertar afiliado
        $dbResult = self::insertAffiliate($decodedParameters);

        // Procesar resultado de la inserción
        if ($dbResult) {
            return ["status" => 201, "message" => "Afiliado registrado"];
        } else {
            throw new ApiException(
                500,
                0,
                "Error del servidor",
                "http://localhost",
                "Error en la base de datos al ejecutar la inserción del afiliado.");
        }
    }

    private static function updateAffiliate() {
        // Obtener parámetros de la petición
        $parameters = file_get_contents('php://input');
        $decodedParameters = json_decode($parameters, true);

        // Controlar posible error de parsing JSON
        if (json_last_error() != JSON_ERROR_NONE) {
            $internalServerError = new ApiException(
                500,
                0,
                "Error interno en el servidor. Contacte al administrador",
                "http://localhost",
                "Error de parsing JSON. Causa: " . json_last_error_msg());
            throw $internalServerError;
        }

        // Insertar afiliado
        $dbResult = self::updateAffiliateData($decodedParameters);

        // Procesar resultado de la inserción
        if ($dbResult) {
            return ["status" => 201, "message" => "Data actualizada"];
        } else {
            throw new ApiException(
                500,
                0,
                "Error del servidor",
                "http://localhost",
                "Error en la base de datos al ejecutar la actualizacion del afiliado.");
        }
    }

    private static function authAffiliate() {
        // Obtener parámetros de la petición
        $parameters = file_get_contents('php://input');
        $decodedParameters = json_decode($parameters, true);

        // Controlar posible error de parsing JSON
        if (json_last_error() != JSON_ERROR_NONE) {
            $internalServerError = new ApiException(500, 0,
                "Error interno en el servidor. Contacte al administrador",
                "http://localhost",
                "Error de parsing JSON. Causa: " . json_last_error_msg());
            throw $internalServerError;
        }

        // Verificar integridad de datos
        if (!isset($decodedParameters["id"]) ||
            !isset($decodedParameters["password"])
        ) {
            throw new ApiException(
                400,
                0,
                "Las credenciales del afiliado deben estar definidas correctamente",
                "http://localhost",
                "El atributo \"id\" o \"password\" o ambos, están vacíos o no definidos"
            );
        }

        $userId = $decodedParameters["id"];
        $password = $decodedParameters["password"];

        // Buscar usuario en la tabla
        $dbResult = self::findAffiliateByCredentials($userId, $password);

        // Procesar resultado de la consulta
        if ($dbResult != NULL) {
            return [
                "status" => 200,
                "id" => $dbResult["id"],
                "name" => $dbResult["name"],
                "numero_cuenta" => $dbResult["numero_cuenta"],
                "telefono" => $dbResult["telefono"],
                "address" => $dbResult["address"],
                "gender" => $dbResult["gender"],
                "token" => $dbResult["token"]
            ];
        } else {
            throw new ApiException(
                400,
                4000,
                "Número de identificación o contraseña inválidos",
                "http://localhost",
                "Puede que no exista un usuario creado con el id:$userId o que la contraseña:$password sea incorrecta."
            );
        }
    }

    private static function insertAffiliate($decodedParameters) {
        //Extraer datos del afiliado
        $id = $decodedParameters["id"];
        $password = $decodedParameters["password"];
        $name = $decodedParameters["name"];
        $numero_cuenta = $decodedParameters["numero_cuenta"];
        $telefono = $decodedParameters["telefono"];
        $address = $decodedParameters["address"];
        $gender = $decodedParameters["gender"];

        // Encriptar contraseña
        $hashPassword = password_hash($password, PASSWORD_DEFAULT);

        // Generar token
        $token = uniqid(rand(), TRUE);

        try {
            $pdo = MysqlManager::get()->getDb();

            // Componer sentencia INSERT
            $sentence = "INSERT INTO affiliate (id, hash_password, name, numero_cuenta, telefono, address, gender, token)" .
                " VALUES (?,?,?,?,?,?)";

            // Preparar sentencia
            $preparedStament = $pdo->prepare($sentence);
            $preparedStament->bindParam(1, $id);
            $preparedStament->bindParam(2, $hashPassword);
            $preparedStament->bindParam(3, $name);
            $preparedStament->bindParam(4, $numero_cuenta);
            $preparedStament->bindParam(5, $telefono);
            $preparedStament->bindParam(6, $address);
            $preparedStament->bindParam(7, $gender);
            $preparedStament->bindParam(8, $token);

            // Ejecutar sentencia
            return $preparedStament->execute();

        } catch (PDOException $e) {
            throw new ApiException(
                500,
                0,
                "Error de base de datos en el servidor",
                "http://localhost",
                "Ocurrió el siguiente error al intentar insertar el afiliado: " . $e->getMessage());
        }
    }

    private static function findAffiliateByCredentials($userId, $password) {
        try {
            $pdo = MysqlManager::get()->getDb();

            // Componer sentencia SELECT
            $sentence = "SELECT * FROM affiliate WHERE id=?";

            // Preparar sentencia
            $preparedSentence = $pdo->prepare($sentence);
            $preparedSentence->bindParam(1, $userId, PDO::PARAM_INT);

            // Ejecutar sentencia
            if ($preparedSentence->execute()) {
                $affiliateData = $preparedSentence->fetch(PDO::FETCH_ASSOC);

                // Verificar contraseña
                if (password_verify($password, $affiliateData["hash_password"])) {
                    return $affiliateData;
                } else {
                    return null;
                }

            } else {
                throw new ApiException(
                    500,
                    5000,
                    "Error de base de datos en el servidor",
                    "http://localhost",
                    "Hubo un error ejecutando una sentencia SQL en la base de datos. Detalles:" . $pdo->errorInfo()[2]
                );
            }

        } catch (PDOException $e) {
            throw new ApiException(
                500,
                0,
                "Error de base de datos en el servidor",
                "http://localhost.com",
                "Ocurrió el siguiente error al consultar el afiliado: " . $e->getMessage());
        }
    }

    private static function updateAffiliateData($decodedParameters) {
        try {
            $pdo = MysqlManager::get()->getDb();

            //Extraer datos del Array
            $name = $decodedParameters["name"];
            $numero_cuenta = $decodedParameters["numero_cuenta"];
            $telefono = $decodedParameters["telefono"];
            $address = $decodedParameters["address"];
            $gender = $decodedParameters["gender"];
    
            // Componer sentencia SELECT
            $sentence = "UPDATE 'affiliate' SET
            'name' = '$name'
            'numero_cuenta' = '$numero_cuenta'
            'telefono' = '$telefono'
            'address' = '$address'
            'gender' = '$gender'";
    
            // Preparar sentencia
            $preparedSentence = $pdo->prepare($sentence);

    
            // Ejecutar sentencia
            if ($preparedSentence->execute()) {
                return true;
    
            } else {
                throw new ApiException(
                    500,
                    5000,
                    "Error de base de datos en el servidor",
                    "http://localhost",
                    "Hubo un error ejecutando una sentencia SQL en la base de datos. Detalles:" . $pdo->errorInfo()[2]
                );
            }
    
        } catch (PDOException $e) {
            throw new ApiException(
                500,
                0,
                "Error de base de datos en el servidor",
                "http://localhost.com",
                "Ocurrió el siguiente error al consultar el afiliado: " . $e->getMessage());
        }
    }

}