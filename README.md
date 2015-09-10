# Conn
Classes para Conexão e Consulta ao Banco de Dados MySQL

- <b>Connection</b>: Efetua a Conexão ao Banco de Dados usando PDO
- <b>Query</b>: Executa querys no Banco de Dados usando as melhores práticas anti SQL Injection
- <b>CreateTable</b>: Classe auxiliar para criação de tabelas (ainda em fase de testes)


## Exemplos de uso

    use Conn;

### Connection

    // Exemplo de conexão local
    $conn = new Connection( 'BD' );

    // Exemplo de  conexão externa
    $conn = $conn = new Connection( 'BD', 'host', 'user', 'pass' );

    
### Query
    
    // Seta a conexão para o script
    Query::setConn( $conn );
    
    // Não conseguiu fazer o insert?
    if( ! Query::exec('INSERT tabela (campo1, campo2) VALUES (:campo1, :campo2)', ['campo1' => 'valor','campo2' => 25.99]))
        // Mostra o log de execução completo
        var_dump( Query::getLog() );
        
    // Efetua uma consulta
    $consulta = Query('SELECT * FROM tabela');
    foreach( $consulta as $linha )
        echo $linha['campo'];
        

### CreateTable
    
    $tabela = new CreateTable( $conn );
    
    // Seta a tabela a ser criada. 
    if( ! $tabela->setTable( 'tabela' ); )
        die('Tabela já existe'); 
    
    // Seta os campos 
    $tabela->addField( 'campo1', CreateTable::TYPE_CHAR, 50 );
    $tabela->addField( 'campo2', CreateTable::TYPE_DECIMAL, '10,2', 9999.99 );
    
    // Seta a chave primária, informando que NÃO é AUTO_INCREMENT 
    $tabela->setPrimaryKey( 'campo1', false );
    
    // Cria a tabela com collate UTF-8 e engine InnoDB
    $retorno = $tabela->create();
    
    // Deu erro?
    if( $retorno !== true )
        echo $retorno;
