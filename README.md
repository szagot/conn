# Conn
Classes para Conexão e Consulta ao Banco de Dados MySQL

- <b>Connection</b>: Efetua a Conexão ao Banco de Dados usando PDO
- <b>Query</b>: Executa querys no Banco de Dados usando as melhores práticas anti SQL Injection
- <b>CreateTable</b>: Classe auxiliar para criação de tabelas (ainda em fase de testes)


## Exemplos de uso

### Connection

    // Exemplo de conexão local
    $conn = new Conn\Connection( 'BD' );

    // Exemplo de  conexão externa
    $conn = $conn = new Conn\Connection( 'BD', 'host', 'user', 'pass' );

    
### Query
    
    // Seta a conexão para o script
    Conn\Query::setConn( $conn );
    
    // Não conseguiu fazer o insert?
    if( ! Conn\Query::exec('INSERT tabela (campo1, campo2) VALUES (:campo1, :campo2)', ['campo1' => 'valor','campo2' => 25.99]))
        // Mostra o log de execução completo
        var_dump( Conn\Query::getLog() );
        
    // Efetua uma consulta
    $consulta = Conn\Query('SELECT * FROM tabela');
    foreach( $consulta as $linha )
        echo $linha['campo'];
        

### CreateTable
    
    $tabela = new Conn\CreateTable( $conn );
    
    // Seta a tabela a ser criada. 
    if( ! $tabela->setTable( 'tabela' ); )
        die('Tabela já existe'); 
    
    // Seta os campos 
    $tabela->addField( 'campo1', Conn\CreateTable::TYPE_CHAR, 50 );
    $tabela->addField( 'campo2', Conn\CreateTable::TYPE_DECIMAL, '10,2', 9999.99 );
    
    // Seta a chave primária, informando que NÃO é AUTO_INCREMENT 
    $tabela->setPrimaryKey( 'campo1', false );
    
    // Cria a tabela com collate UTF-8 e engine InnoDB
    $tabela->create();