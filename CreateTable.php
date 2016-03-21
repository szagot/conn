<?php
/**
 * Classe para criar tabelas no banco de dados.
 *
 * NOTA: Se houver erro na conexão (Connection não setado), o script será interrompido e o erro mostrado em uma tag
 * META, que é invisível ao usuário, sendo visível apenas no código da página
 *
 * @author    Daniel Bispo <szagot@gmail.com>
 * @copyright Copyright (c) 2015
 */

namespace Conn;

class CreateTable
{
    /** @const Chaves Estrangeiras */
    const
        FK_RESTRICT = 'RESTRICT',   # Não permite deleçao
        FK_CASCADE = 'CASCADE',     # Apaga/atualiza os registros da tabela filha associada
        FK_NULL = 'SET NULL',       # Seta como NULL o campo associado ao registro apagado/atualizado
        FK_NONE = 'NO ACTION';      # Não faz nada

    /** @cnst Criação da Tabela */
    const
        COLLATE_UTF8 = 'utf8_general_ci',
        COLLATE_LATIN = 'latin1_swedish_ci',
        ENGINE_INNODB = 'InnoDB',
        ENGINE_MYISSAM = 'MyISAM';

    /** @const Tipos de campos */
    const
        // Integer
        TYPE_TINYINT = 'TINYINT',
        TYPE_SMALLINT = 'SMALLINT',
        TYPE_MEDIUMINT = 'MEDIUMINT',
        TYPE_INT = 'INT',
        TYPE_BIGINT = 'BIGINT',
        TYPE_BIT = 'BIT',
        // Real
        TYPE_FLOAT = 'FLOAT',
        TYPE_DOUBLE = 'DOUBLE',
        TYPE_DECIMAL = 'DECIMAL',
        // Text
        TYPE_CHAR = 'CHAR',
        TYPE_VARCHAR = 'VARCHAR',
        TYPE_TINYTEXT = 'TINYTEXT',
        TYPE_TEXT = 'TEXT',
        TYPE_MEDIUMTEXT = 'MEDIUMTEXT',
        TYPE_LONGTEXT = 'LONGTEXT',
        // Binary
        TYPE_BINARY = 'BINARY',
        TYPE_VARBINARY = 'VARBINARY',
        TYPE_TINYBLOB = 'TINYBLOB',
        TYPE_BLOB = 'BLOB',
        TYPE_MEDIUMBLOB = 'MEDIUMBLOB',
        TYPE_LONGBLOB = 'LONGBLOB',
        // Temporal (time)
        TYPE_DATE = 'DATE',
        TYPE_TIME = 'TIME',
        TYPE_YEAR = 'YEAR',
        TYPE_DATETIME = 'DATETIME',
        TYPE_TIMESTAMP = 'TIMESTAMP',
        // Spacial
        TYPE_POINT = 'POINT',
        TYPE_LINESTRING = 'LINESTRING',
        TYPE_POLYGON = 'POLYGON',
        TYPE_GEOMETRY = 'GEOMETRY',
        TYPE_MULTIPOINT = 'MULTIPOINT',
        TYPE_MULTILINESTRING = 'MULTILINESTRING',
        TYPE_MULTIPOLYGON = 'MULTIPOLYGON',
        TYPE_GEOMETRYCOLLECTION = 'GEOMETRYCOLLECTION',
        // Others
        TYPE_ENUM = 'ENUM',
        TYPE_SET = 'SET';

    private
        $conn,
        $tableName = '',
        $fields = [ ],
        $primaryKey = '',
        $autoIncrement = true,
        $indexKeys = [ ],
        $uniqueKeys = [ ],
        $fullTextKeys = [ ],
        $fKeys = [ ];

    /**
     * CreateTable constructor.
     *
     * @param Connection $conn Conexão com o BD
     */
    public function __construct( Connection $conn = null )
    {
        // Conexão foi setada?
        if ( $conn )
            $this->setConn( $conn );
    }

    /**
     * Seta a Conexão ao BD desejado. Só é necessário uma vez, a menos que deseje mudar o BD.
     *
     * @param Connection $conn
     * @param string     $tableName Nome da Tabela
     * @param bool       $dropTable Se a tabela existir no BD, ela deve ser sobescrita?
     */
    public function setConn( Connection $conn, $tableName = '', $dropTable = false )
    {
        // Seta a base de dados para execução das query's
        $this->conn = $conn;
        Query::setConn( $conn );

        if ( $this->validateName( $tableName ) )
            $this->setTable( $tableName, $dropTable );
    }

    /**
     * Adiciona uma nova tabela ao motor de criação.
     * Se ela já tver sido adicionada ao objeto, será sobescrita.
     * Se já existir no BD, retorna FALSE
     *
     * @param string $tableName Nome da Tabela
     * @param bool   $dropTable Se a tabela existir no BD, ela deve ser sobescrita?
     *
     * @return bool Tabela adicionada?
     */
    public function setTable( $tableName, $dropTable = false )
    {
        // Está dentro dos padrões o nome da tabela?
        if ( ! $this->validateName( $tableName ) )
            return false;

        // Se a tabela já existir no banco de dados, não autoriza a inserção, a menos que se queira excluí-la
        if ( $this->tableExists( $tableName ) && ! $dropTable )
            return false;

        // Adiciona a tabela ao motor
        $this->tableName = $tableName;

        return true;

    }

    /**
     * Adiciona um campo ao motor
     *
     * @param string $fieldName    Nome do Campo
     * @param string $type         Tipo do campo (char, decimal, text, ...)
     * @param mixed  $len          Tamanho do campo (string no formato '10,2' para decimal)
     * @param mixed  $defaultValue Valor padrão do campo
     *
     * @return bool
     */
    public function addField( $fieldName, $type = self::TYPE_INT, $len = null, $defaultValue = null )
    {
        // Está dentro dos padrões o nome do campo?
        if ( ! $this->validateName( $fieldName ) )
            return false;

        // Adicionando o campo
        $this->fields[ $fieldName ] = [
            // Tipo + Tamanho do campo, se aplicável
            'type'         => $type . ( preg_match( '/^[1-9][0-9,]*$/', $len ) ? "($len)" : '' ),
            'defaultValue' => $defaultValue
        ];

        return true;
    }

    /**
     * Seta a chave primária
     *
     * @param string $fieldName     Nome do campo a ter a chave setada
     * @param bool   $autoIncrement Deve auto-incrmentar o campo? Válido apenas para campos numéricos
     *
     * @return bool
     */
    public function setPrimaryKey( $fieldName, $autoIncrement = true )
    {
        // Verifica se o campo foi setado
        if ( ! array_key_exists( $fieldName, $this->fields ) )
            return false;

        $this->primaryKey = $fieldName;
        $this->autoIncrement = $autoIncrement;

        return true;
    }

    /**
     * Adiciona um index ao campo
     *
     * @param string $fieldName Nome do campo a ter a chave adicionada
     *
     * @return bool
     */
    public function addKey( $fieldName )
    {
        // Verifica se o campo foi setado
        if ( ! array_key_exists( $fieldName, $this->fields ) )
            return false;

        // Se já foi adicionado, não adiciona novamente
        if ( in_array( $fieldName, $this->indexKeys ) )
            return true;

        $this->indexKeys[] = $fieldName;

        return true;
    }

    /**
     * Adiciona uma chave única ao campo
     *
     * @param string $fieldName Nome do campo a ter a chave adicionada
     *
     * @return bool
     */
    public function addUniqueKey( $fieldName )
    {
        // Verifica se o campo foi setado
        if ( ! array_key_exists( $fieldName, $this->fields ) )
            return false;

        // Se já foi adicionado, não adiciona novamente
        if ( in_array( $fieldName, $this->uniqueKeys ) )
            return true;

        $this->uniqueKeys[] = $fieldName;

        return true;
    }

    /**
     * Adiciona uma chave do tipo full text ao campo
     *
     * @param string $fieldName Nome do campo a ter a chave adicionada
     *
     * @return bool
     */
    public function addFullTextKey( $fieldName )
    {
        // Verifica se o campo foi setado
        if ( ! array_key_exists( $fieldName, $this->fields ) )
            return false;

        // Se já foi adicionado, não adiciona novamente
        if ( in_array( $fieldName, $this->fullTextKeys ) )
            return true;

        $this->fullTextKeys[] = $fieldName;

        return true;
    }

    /**
     * Cria uma chave estrangeira para uma outra tabela
     *
     * @param string $fieldName Nome do campo da tabela atual
     * @param string $tableFk   Nome da tabela estrangeira (já criada)
     * @param string $fieldFk   Nome do campo a ser associado (deve ser do mesmo tipo)
     * @param string $delete    Ação para deleção de registro
     * @param string $update    Ação para update de registro
     *
     * @return boolean Chave criada?
     */
    public function addFk( $fieldName, $tableFk, $fieldFk, $delete = self::FK_RESTRICT, $update = self::FK_RESTRICT )
    {
        // Verifica se o campo foi setado e se o campo estrangeiro passa nos padrões
        if ( ! array_key_exists( $fieldName, $this->fields ) )
            return false;

        // Tabela/Campo externa existem?
        if ( ! $this->fieldExists( $tableFk, $fieldFk ) )
            return false;

        // Adiciona a chave ao campo estrangeiro
        $this->addKey( $fieldName );
        $this->fKeys[] = [
            'field'   => $fieldName,
            'tableFk' => $tableFk,
            'fieldFk' => $fieldFk,
            'delete'  => $delete,
            'update'  => $update
        ];

        return true;
    }

    /**
     * Cria a tabela.
     * Lembrando que, se mudar o engine para MyIsam, qualquer chave estrangeira criada sera ignorada.
     *
     * @param string $collate Formato do texto, colação da tabela
     * @param string $engine  Motor da tabela.
     *
     * @return string|boolean Retorna TRUE em caso de sucesso ou uma mensagem de erro em caso de falha.
     */
    public function create( $collate = self::COLLATE_UTF8, $engine = self::ENGINE_INNODB )
    {
        // Tabela setada?
        if ( ! $this->validateName( $this->tableName ) )
            return 'Você precisa definir o nome da tabela a ser criada. '
            . 'Não use caracteres especiais, apenas letras, números, traços e underline, '
            . 'e deve iniciar com uma letra.';

        // Pelo menos 1 campo foi setado?
        if ( count( $this->fields ) == 0 )
            return 'Você deve adicionar pelo menos 1 campo. '
            . 'Não use caracteres especiais, apenas letras, números, traços e underline, '
            . 'e deve iniciar com uma letra.';

        // Inser os campos
        $fields = '';
        foreach ( $this->fields as $fieldName => $data )
            $fields .= ( $fields == '' ? '' : ', ' )
                . " `{$fieldName}` {$data['type']}"
                . ( ( $this->primaryKey == $fieldName )
                    ? ( ' NOT NULL' . ( $this->autoIncrement ? ' AUTO_INCREMENT' : '' ) )
                    : ( empty( $data[ 'defaultValue' ] ) ? ' NULL' : " NOT NULL DEFAULT '{$data[ 'defaultValue' ]}'" ) );

        // Define a primary key
        $pk = '';
        if ( ! empty( $this->primaryKey ) )
            $pk = ", PRIMARY KEY (`{$this->primaryKey}`)";

        // Define as chaves
        $keys = '';
        // Index
        if ( count( $this->indexKeys ) )
            foreach ( $this->indexKeys as $key )
                $keys .= ", INDEX `$key` (`$key`)";
        // Unique
        if ( count( $this->uniqueKeys ) )
            foreach ( $this->uniqueKeys as $key )
                $keys .= ", UNIQUE INDEX `$key` (`$key`)";
        // Fulltext
        if ( count( $this->fullTextKeys ) )
            foreach ( $this->fullTextKeys as $key )
                $keys .= ", FULLTEXT INDEX `$key` (`$key`)";

        // Define as chaves estrangeiras
        $fk = '';
        if ( count( $this->fKeys ) > 0 && $engine == self::ENGINE_INNODB )
            foreach ( $this->fKeys as $index => $fKey )
                $fk .= ", CONSTRAINT `FK_{$this->tableName}_{$index}` "
                    . "FOREIGN KEY (`{$fKey['field']}`) "
                    . "REFERENCES `{$fKey['tableFk']}` (`{$fKey['fieldFk']}`) "
                    . "ON UPDATE {$fKey['update']} "
                    . "ON DELETE {$fKey['delete']}";

        // Monta query
        $query = "CREATE TABLE `{$this->tableName}` ( {$fields} {$pk} {$keys} {$fk} ) COLLATE = '{$collate}' ENGINE = {$engine}";

        // Se a tabela existir, é porque setTable foi configurado para permitir deleção da tabela. Neste caso, tenta apagar
        if ( $this->tableExists( $this->tableName ) ) {
            // Remove a checagem de chaves estrangeiras
            Query::exec( 'SET FOREIGN_KEY_CHECKS=0' );
            // Tenta excluir
            if ( ! Query::exec( "DROP TABLE `{$this->tableName}`" ) )
                return "A tabela {$this->tableName} existe e não foi possível apagar. Erro: " . Query::getLog( true )[ 'errorMsg' ];
        }

        // Tenta criar a tabela
        if ( ! Query::exec( $query ) )
            return "Não foi possível criar a tabela {$this->tableName}. Erro: " . Query::getLog( true )[ 'errorMsg' ];

        return true;
    }

    /**
     * Verifica existência de tabela no BD
     *
     * @param string $tableName Nome da tabela
     *
     * @return bool
     */
    public function tableExists( $tableName )
    {
        // Efetua pesquisa em busca da tabela requisitada
        $consulta = Query::exec( "
          SELECT
            COUNT(TABLE_NAME) AS `table`
          FROM
            INFORMATION_SCHEMA.TABLES
          WHERE
            TABLE_SCHEMA = '{$this->conn->getSchema()}' AND TABLE_NAME LIKE '{$tableName}'
        " )[ 0 ];

        // Tabela existe?
        return (int) $consulta[ 'table' ] == 1;
    }


    /**
     * Valida se o nome da tabela ou do campo está dentro dos padrões - apenas letras, números, underline e/ou traço
     * e deve começar com uma letra.
     *
     * @param string $name Nome da tabela ou do campo a ser validado
     *
     * @return boolean
     */
    private function validateName( $name = '' )
    {
        return preg_match( '/^[a-z][a-z0-9_-]*$/i', $name );
    }

    /**
     * Verifica a existência de um campo
     *
     * @param string $tableName Nome da tabela a ser verificada
     * @param string $fieldName Nome do campo a ser verificado
     *
     * @return boolean Existe o campo?
     */
    private function fieldExists( $tableName, $fieldName )
    {

        // Os nomes passados estão no padrão?
        if ( ! $this->validateName( $tableName ) || ! $this->validateName( $fieldName ) )
            return false;

        // Pesquisa o campo na tabela
        $consulta = Query::exec( "SHOW COLUMNS FROM $tableName WHERE Field = '$fieldName'" );

        // O campo foi encontrado?
        return isset( $consulta[ 0 ][ 'Field' ] );

    }

}