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

### Contributors ✨

<!-- ALL-CONTRIBUTORS-BADGE:START - Do not remove or modify this section -->
[![All Contributors](https://img.shields.io/badge/all_contributors-6-orange.svg?style=flat-square)](#contributors-)
<!-- ALL-CONTRIBUTORS-BADGE:END -->

Thanks goes to these wonderful people ([emoji key](https://allcontributors.org/docs/en/emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->
<table>
  <tr>
    <td align="center"><a href="https://twitter.com/malukenho"><img src="https://avatars2.githubusercontent.com/u/3275172?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Jefersson Nathan</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/commits?author=malukenho" title="Code">💻</a> <a href="#maintenance-malukenho" title="Maintenance">🚧</a></td>
    <td align="center"><a href="http://eher.com.br"><img src="https://avatars0.githubusercontent.com/u/398034?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Alexandre Eher</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/commits?author=eher" title="Code">💻</a></td>
    <td align="center"><a href="https://airton.dev"><img src="https://avatars1.githubusercontent.com/u/6540546?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Airton Zanon</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/pulls?q=is%3Apr+reviewed-by%3Aairtonzanon" title="Reviewed Pull Requests">👀</a></td>
    <td align="center"><a href="https://github.com/wpeereboom"><img src="https://avatars1.githubusercontent.com/u/516326?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Winfred Peereboom</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/issues?q=author%3Awpeereboom" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://github.com/batusa"><img src="https://avatars3.githubusercontent.com/u/5388003?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Emmerson Siqueira</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/pulls?q=is%3Apr+reviewed-by%3Abatusa" title="Reviewed Pull Requests">👀</a></td>
    <td align="center"><a href="https://github.com/echevalaz"><img src="https://avatars.githubusercontent.com/u/52658226?v=4?s=100" width="100px;" alt=""/><br /><sub><b>echevalaz</b></sub></a><br /><a href="https://github.com/codelicia/trineforce/issues?q=author%3Aechevalaz" title="Bug reports">🐛</a> <a href="#ideas-echevalaz" title="Ideas, Planning, & Feedback">🤔</a></td>
  </tr>
</table>

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->

<!-- ALL-CONTRIBUTORS-LIST:END -->

This project follows the [all-contributors](https://github.com/all-contributors/all-contributors) specification. Contributions of any kind welcome!

### Author

- Jefersson Nathan ([@malukenho](http://github.com/malukenho))
- Alexandre Eher ([@Eher](http://github.com/malukenho))
