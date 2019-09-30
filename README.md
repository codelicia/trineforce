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
    'user' => 'salesforce-user@email.com',
    'password' => 'salesforce-password',
    'consumerKey' => '...',
    'consumerSecret' => '...',
    'driverClass' => \Codelicia\Soql\SoqlDriver::class,
    'wrapperClass' => \Codelicia\Soql\ConnectionWrapper::class,
];

/** @var \Codelicia\Soql\ConnectionWrapper $conn */
$conn = DriverManager::getConnection($connectionParams, $config);
``` 

* `user` provides the login, which is usually an email to access the salesforce
  instance.
* `password` provides the corresponding password to the email provided on `user`.
* `salesforceInstance` points to the url of the Salesforce instance.
* `consumerKey` provides the integration consumer key
* `consumerSecret` provides the integration consumer secret
* `driverClass` should points to `\Codelicia\Soql\SoqlDriver::class`
* `wrapperClass` should points to `\Codelicia\Soql\ConnectionWrapper::class`

By setting up the `wrapperClass`, we can make use of a proper `QueryBuild` that allow
`JOIN` in the Salesforce format.

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

### Author

- Jefersson Nathan ([@malukenho](http://github.com/malukenho))
