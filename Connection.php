<?php
/**
 * Efetua a conexão com o Banco de Dados utilizando PDO
 * Configurações da conexão:
 *      MySQL Database
 *      Charset = UTF-8 (sugestão de COLLATE: utf8_general_ci)
 *      Error Mode = PDOException
 *      Persitencia na Conexão = Sim
 *
 * NOTA: Se houver erro na conexão, o script será interrompido e o erro mostrado em uma tag META,
 * que é invisível ao usuário, sendo visível apenas no código da página
 *
 * @author    Daniel Bispo <szagot@gmail.com>
 * @copyright Copyright (c) 2015
 */

namespace Conn;

use \PDOException,
    \PDO;

class Connection
{
    private $conn, $db;

    /**
     * Connection constructor.
     *
     * @param string $db   Define o Banco de Dados
     * @param string $host Define o Host
     * @param string $user Define o usuário
     * @param string $pass Define a senha
     */
    public function __construct( $db, $host = 'localhost', $user = 'root', $pass = '' )
    {
        try {

            $this->conn = new PDO( "mysql:host={$host};dbname={$db};charset=utf8", $user, $pass, [
                // Garante a conversão par UTF-8
                // É necessário que o banco de dados também seja criado com UTF-8 e cada tabela com COLLATE='utf8_general_ci'
                // EX.: CREATE DATABASE nome_bd CHARACTER SET UTF8;
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8',
                // Recepciona os erros com PDOException
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Mantém aberta a Conexão com o Banco de Dados, se possível
                PDO::ATTR_PERSISTENT         => true
            ] );

            // Salva o nome do BD conectado para posterior consulta de schema
            $this->db = $db;

        } catch ( PDOException $err ) {
            die( "<meta name='erro_conexao' content='Erro ao conectar com o PDO: {$err->getMessage()}' />" );
        }

    }

    /**
     * Connection destructor
     */
    function __destruct()
    {
        // Desfaz a coneção com o PDO
        $this->conn = null;
    }


    /**
     * Pega o resultado da conexão
     *
     * @return PDO
     */
    public function getConn()
    {
        return $this->conn;
    }

    /**
     * Retorna o Schema (nome do BD) da conexão atual
     *
     * @return string
     */
    public function getSchema()
    {
        return $this->db;
    }


}