<?php
/**
 * Executa uma consulta em banco de dados com segurança
 *
 * A flexibilidade desta classe lhe permite fazer consultas em multiplos BD no mesmo script. Exemplo:
 * $bd1 = new Connection('database1');
 * $bd2 = new Connection('database2');
 * $consulta1 = Query::exec('SELECT...', [], $bd1);
 * $consulta2 = Query::exec('SELECT...', [ 'campo1' => 'valor' ], $bd2);
 *
 * NOTA: Se houver erro na conexão (Connection não setado), o script será interrompido e o erro mostrado em uma tag
 * META, que é invisível ao usuário, sendo visível apenas no código da página
 *
 * @author    Daniel Bispo <szagot@gmail.com>
 * @copyright Copyright (c) 2015
 */

namespace Conn;

use
    \PDOException,
    \PDO;

class Query
{
    private static
        $conn,
        $log = [ ];

    /**
     * Executa uma consulta ao Banco de Dados.
     * Em caso de consulta segura, segue-se o mesmo padrão do PDO, informando-se o valor das chaves em $params
     * Exemplo: $consulta = Query::exec( 'SELECT * FROM tabela WHERE id = :idValue', [ 'idValue' => 25 ] );
     *
     * NOTA: Por padrão, os dados dos parâmetros, quando string, sofrerão a remoção de quaisquer tags html, a menos que
     * a chave do parâmetro venha acompanhado de asterisco(*). Exemplo: $params = ['desc*' => '<p>...</p>'];
     *
     * ATENÇÃO! É necessário que a conexão ao BD tenha sido informado em algum momento antes com self::setConn,
     * ou através do parametro $conn. Exemplo: Query::exec('SELECT ...', [], new Connection(...) );
     *
     * @param string          $sql  Comando SQL
     * @param array           $params
     * @param Connection|null $conn Conexão da consulta, caso não tenha sido setada ainda
     *
     * @return boolean|array Em caso de sucesso retorna TRUE ou um array associativo em caso de SELECT
     */
    public static function exec( $sql, $params = [ ], Connection $conn = null )
    {
        // Conexão ao BD informado?
        if ( $conn )
            self::setConn( $conn );

        if ( ! self::$conn )
            die( "<meta name='erro_conexao' content='Efetue uma conexão primeiro' />" );

        $erro =
        $query =
        $lastId =
        $rowsAffected = null;
        try {
            // Prepara a query
            $query = self::$conn->getConn()->prepare( $sql );

            if ( count( $params ) > 0 )
                foreach ( $params as $campo => $valor )
                    // É nulo?
                    if ( is_null( $valor ) )
                        $query->bindValue( ':' . $campo, null, PDO::PARAM_NULL );
                    // O valor é booleano?
                    elseif ( is_bool( $valor ) )
                        $query->bindValue( ':' . $campo, $valor, PDO::PARAM_BOOL );
                    // O valor é inteiro?
                    elseif ( is_int( $valor ) )
                        $query->bindValue( ':' . $campo, $valor, PDO::PARAM_INT );
                    // É string, mas permite HTML? (Ou seja, tem * no campo)
                    elseif ( preg_match( '/\*$/', $campo ) )
                        $query->bindValue( ':' . str_replace( '*', '', $campo ), $valor, PDO::PARAM_STR );
                    // É apenas string?
                    else
                        $query->bindValue( ':' . $campo, strip_tags( trim( $valor ) ), PDO::PARAM_STR );

            // Executa a query
            $query->execute();

            // Número de Linhas Afetadas pela Query
            $rowsAffected = $query->rowCount();

            // Sendo um INSERT ou REPLACE, retorna o último ID inserido
            if ( preg_match( '/^[\n\r\s\t]*(insert|replace)/is', $sql ) )
                $lastId = self::$conn->getConn()->lastInsertId();

        } catch ( PDOException $e ) {

            $erro = $e->getMessage();

        }

        // Cria o log da execução
        self::makeLog( $sql, $params, $lastId, $rowsAffected, $erro );

        if ( $erro )
            return false;

        // Retorno em um array associadtivo quando a Query for um SELECT ou um SHOW
        if ( preg_match( '/^[\n\r\s\t]*(select|show)/is', $sql ) )
            return $query->fetchAll( PDO::FETCH_ASSOC );

        // Query executada
        return true;
    }

    /**
     * Seta a Conexão ao BD desejado. Só é necessário uma vez, a menos que deseje mudar o BD.
     *
     * @param Connection $conn
     */
    public static function setConn( Connection $conn )
    {
        self::$conn = $conn;
    }

    /**
     * Pega todos os códigos SQL's executados durante o script. Cada posição do retorno conterá:
     *      [
     *          'schema' => 'bancdo_de_dados', // Base de dados em que a query foi executada
     *          'dateTime' => 'Y-m-d H:i:s', // Data/Hora da execução
     *          'sql' => 'CODIGO SQL EXECUTADO JÁ COM PARAMETROS SUBSTITUIDOS', // Retornou erro?
     *          'lastId' => null, // Último id inserido em caso de INSERT ou REPLACE
     *          'rowsAffected' => 99, // Quantidade de linhas afetadas pela query
     *          'error' => true|false, // Houve erro nesta execução?
     *          'errorMsg' => 'Mensagem do Erro',
     *          // A seguir seguem os dados originais informados
     *          'data' => [
     *                  'sql' => 'CODIGO SQL COMO INFORMADO',
     *                  'params' => [] // Parâmetros de substituição informados
     *              ]
     *      ]
     *
     * @param boolean|false $apenasUltimo Apenas o último SQL deve ser retornado?
     *
     * @return array
     */
    public static function getLog( $apenasUltimo = false )
    {
        return $apenasUltimo ? end( self::$log ) : self::$log;
    }

    /**
     * Cria log de execução
     *
     * @param string $sql          Query executada
     * @param null   $lastId       Último id inserido
     * @param int    $rowsAffected Quantidade de linhas afetadas
     * @param string $error        Erros
     */
    private static function makeLog( $sql, $params = [ ], $lastId = null, $rowsAffected = 0, $error = '' )
    {
        $sqlOriginal = $sql;

        // Tem parametros?
        if ( count( $params ) > 0 )
            foreach ( $params as $campo => $valor )
                // É nulo?
                if ( is_null( $valor ) )
                    $sql = str_replace( ':' . $campo, 'NULL', $sql );
                // É vazio?
                elseif ( empty( $valor ) )
                    $sql = str_replace( ':' . $campo, '""', $sql );
                // O valor é booleano ou numerico?
                elseif ( is_bool( $valor ) || is_int( $valor ) )
                    $sql = str_replace( ':' . $campo, $valor, $sql );
                // É string, mas permite HTML? (Ou seja, tem * no campo)
                elseif ( preg_match( '/\*$/', $campo ) )
                    $sql = str_replace( ':' . str_replace( '*', '', $campo ), '"' . $valor . '"', $sql );
                // É apenas string
                else
                    $sql = str_replace( ':' . $campo, '"' . strip_tags( trim( $valor ) ) . '"', $sql );

        // Monta o Log
        self::$log[] = [
            'schema'       => self::$conn->getSchema(),
            'dateTime'     => date( 'Y-m-d H:i:s' ),
            'sql'          => $sql,
            'lastId'       => $lastId,
            'rowsAffected' => $rowsAffected,
            'error'        => ! empty( $error ),
            'errorMsg'     => $error,
            'data'         => [
                'sql'    => $sqlOriginal,
                'params' => $params
            ]
        ];
    }

    /**
     * Exec constructor.
     */
    private function __construct()
    {
        // Impede que a classe seja instanciada
    }

}