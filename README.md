SOQL Doctrine DBAL
==================

**Salesforce Soql Doctrine Driver** allows you to write Soql queries
and interact with a Salesforce instance using the Doctrine DBAL layer.

Now one can forget about Salesforce and have a nice `repository/query object`
integration on one's architecture without hurting that much on the usual
project structure.

### Installation

Use `composer` to install this package as bellow:

```shell script
$ composer require codelicia/trineforce
```

### Configuration

If you are familiar with Doctrine, then you probably already know how to
configure and use it. But some special configuration is required in order
to make it work.

When creating a new `Connection`, you should also provide the configuration
keys for `salesforceInstance`, `consumerKey`, `consumerSecret` and point to
the right `driverClass`. The usual `user` and `password` are also required.

```php
$config = new Configuration();
$connectionParams = [
    'salesforceInstance' => 'https://[SALESFORCE INSTANCE].salesforce.com',
    'apiVersion'         => 'v43.0',
    'user'               => 'salesforce-user@email.com',
    'password'           => 'salesforce-password',
    'consumerKey'        => '...',
    'consumerSecret'     => '...',
    'driverClass'        => \Codelicia\Soql\SoqlDriver::class,
    'wrapperClass'       => \Codelicia\Soql\ConnectionWrapper::class,
];

/** @var \Codelicia\Soql\ConnectionWrapper $conn */
$conn = DriverManager::getConnection($connectionParams, $config);
```

* `user` provides the login, which is usually an email to access the salesforce
  instance.
* `password` provides the corresponding password to the email provided on `user`.
* `salesforceInstance` points to the url of the Salesforce instance.
* `apiVersion` specify a salesforce API version to work with.
* `consumerKey` provides the integration consumer key
* `consumerSecret` provides the integration consumer secret
* `driverClass` should points to `\Codelicia\Soql\SoqlDriver::class`
* `wrapperClass` should points to `\Codelicia\Soql\ConnectionWrapper::class`

By setting up the `wrapperClass`, we can make use of a proper `QueryBuild` that allow
`JOIN` in the Salesforce format.

When using the doctrine bundle and the dbal is configured through yaml the options should
be passed in a different way for the validation that the bundle does.

```yaml
doctrine:
    dbal:
        driver: soql
        user: '%env(resolve:SALESFORCE_USERNAME)%'
        password: '%env(resolve:SALESFORCE_PASSWORD)%'
        driver_class: '\Codelicia\Soql\SoqlDriver'
        wrapper_class: '\Codelicia\Soql\ConnectionWrapper'
        options:
            salesforceInstance: '%env(resolve:SALESFORCE_ENDPOINT)%'
            apiVersion: v56.0
            consumerKey: '%env(resolve:SALESFORCE_CLIENT_ID)%'
            consumerSecret: '%env(resolve:SALESFORCE_CLIENT_SECRET)%'
```

### Using DBAL

Now that you have the connection set up, you can use Doctrine `QueryBuilder` to
query some data as bellow:

```php
$id = '0062X00000vLZDVQA4';

$sql = $conn->createQueryBuilder()
    ->select(['Id', 'Name', 'Status__c'])
    ->from('Opportunity')
    ->where('Id = :id')
    ->andWhere('Name = :name')
    ->setParameter('name', 'Pay as you go Opportunity')
    ->setParameter('id', $id)
    ->setMaxResults(1)
    ->execute();

var_dump($sql->fetchAll()); // All rest api result
```

or use the normal `Connection#query()` method.

### Basic Operations

Here are some examples of basic `CRUD` operations.

#### `Connection#insert()`

Creating an `Account` with the `Name` of `John`:
```php
$connection->insert('Account', ['Name' => 'John']);
```

#### `Connection#delete()`

Deleting an `Account` with the `Id` = `1234`:
```php
$connection->delete('Account', ['Id' => '1234']);
```

#### `Connection#update()`

Update an `Account` with the `Name` of `Sr. John` where the `Id` is `1234`:
```php
$connection->update('Account', ['Name' => 'Sr. John'], ['Id' => '1234']);
```

### Be Transactional with `Composite` API

As salesforce released the `composite` api, it gave us the ability
to simulate transactions as in a database. So, we can use the same
Doctrine DBAL api that you already know to do transactional operations
in your Salesforce instance.

```php
$conn->beginTransaction();

$conn->insert('Account', ['Name' => 'John']);
$conn->insert('Account', ['Name' => 'Elsa']);

$conn->commit();
```

Or even, use the `Connection#transactional()` helper, as you prefer.

#### Referencing another Records

The `composite` api, also enables us to compose a structure data to be
changed in one single request. So we can cross reference records as it
fits our needs.

Let's see how to create an `Account` and a linked `Contact` to that `Account`
in a single `composite` request.

```php
$conn->transactional(static function () use ($conn) {

    $conn->insert('Account', ['Name' => 'John'], ['referenceId' => 'account']);
    $conn->insert('Contact', [
        'FirstName' => 'John',
        'LastName' => 'Contact',
        'AccountId' => '@{account.id}' // reference `Account` by its `referenceId`
    ]);

});
```

### 🚫 Known Limitations

As of today, we cannot consume a `sObject` using the `queryBuilder` to get all fields from
the `sObject`. That is because Salesforce doesn't accept `SELECT *` as a valid query.

The workaround that issue is to do a `GET` request to specific resources, then can grab all
data related to that resource.

```php
$this->connection
    ->getNativeConnection() // : \GuzzleHttp\ClientInterface
    ->request(
        'GET',
        sprintf('/services/data/v40.0/sobjects/Opportunity/%s', $id)
    )
    ->getBody()
    ->getContents()
;
```

### 📈 Diagram

```mermaid
%%{init: {'sequence': { 'mirrorActors': false, 'rightAngles': true, 'messageAlign': 'center', 'actorFontSize': 20, 'actorFontWeight': 900, 'noteFontSize': 18, 'noteFontWeight': 600, 'messageFontSize': 20}}}%%
%%{init: {'theme': 'base', 'themeVariables': { 'actorBorder': '#D86613', 'activationBorderColor': '#232F3E', 'activationBkgColor': '#D86613','noteBorderColor': '#232F3E', 'signalColor': 'white', 'signalTextColor': 'gray', 'sequenceNumberColor': '#232F3E'}}}%%
sequenceDiagram
    autonumber
    Note left of ConnectionWrapper: Everything starts with <br/>the ConnectionWrapper.
    ConnectionWrapper->>QueryBuilder: createQueryBuilder()
    activate QueryBuilder
    alt 
        QueryBuilder->>QueryBuilder: execute() <br>Calls private executeQuery()<br>method
    end
    QueryBuilder->>+ConnectionWrapper: executeQuery()
    deactivate QueryBuilder
    ConnectionWrapper->>SoqlStatement: execute() 
    SoqlStatement->>+\Doctrine\DBAL\Driver\Result: execute()
    ConnectionWrapper->>+\Codelicia\Soql\DBAL\Result: new
    \Doctrine\DBAL\Driver\Result-->>\Codelicia\Soql\DBAL\Result: pass to
    \Codelicia\Soql\DBAL\Result-->>-ConnectionWrapper: returns
    ConnectionWrapper->>-SoqlStatement: fetchAll()
    SoqlStatement->>+\Codelicia\Soql\FetchDataUtility: fetchAll()
    \Codelicia\Soql\FetchDataUtility-->>+\GuzzleHttp\ClientInterface: send()
    Note right of \Codelicia\Soql\FetchDataUtility: Countable goes here?<br> before creating the Payload?
    \Codelicia\Soql\FetchDataUtility->>+\Codelicia\Soql\Payload: new
    \Codelicia\Soql\Payload-->>+SoqlStatement: returns
```

### Author

- Jefersson Nathan ([@malukenho](http://github.com/malukenho))
- Alexandre Eher ([@Eher](http://github.com/malukenho))
