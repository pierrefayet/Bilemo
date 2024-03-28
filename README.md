# Bilemo Project

[![SymfonyInsight](https://insight.symfony.com/projects/7c26401c-056c-450c-86c8-8e182a4edb48/small.svg)]

## Description

Bilemo is an innovative API solution designed to provide a mobile phone catalog to resale platforms.
Leveraging modern standards, Bilemo aims to simplify the integration and catalog management process for its business
partners.

## Features

- Mobile Catalog: Access a wide range of mobile phones, complete with detailed information for each product.
- User Management: Allows partner platforms to manage their users.
- Secure Authentication: Utilizes JWT (JSON Web Tokens) to secure access to the API.
- snowboard tricks gallery with tutorials posted by members
- API Documentation: Comprehensive documentation available through NelmioApiDoc for easy integration.

## Technologies Used

- Symfony 7
- PHP 8.2
- Doctrine ORM
- LexikJWTAuthenticationBundle
- NelmioApiDocBundle

## Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL or any other database management system compatible with Doctrine

## Installation

```bash
# Clone 
git clone https://github.com/pierrefayet/Projet-7-Bilemo.git
cd Projet-7-Bilemo
composer install
php bin/console doctrine:database:create
# Create the BDD and import the schema
php bin/console doctrine:schema:update --force
# Starting the Symfony server
symfony server:start
```

## Utilisation

After installation, open your browser and go to https://localhost:8000/api/doc to learn to consume the Bilemo Api.

## Contribution

Contributions are what make the open source community a place of learning, inspiration and creativity. We encourage
contributions large and small.

## Authors
Pierre Fayet
