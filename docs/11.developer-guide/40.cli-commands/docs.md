---
title: CLI commands
taxonomy:
    category: docs
---
## Overview
Atrocore system provides several CLI commands. Some of them help to execute functions manually, others help with debugging.

## How to use
To see all the possible CLI commands in your version of Atrocore you should run:
```
php console.php list
```
Here you will see all posible commands and a description for of each one.

## Example
For example, to check if the database schema is OK, you should run:
```
php console.php sql diff --show
```
The system will display SQL commands that need to be executed, or tell us that the database schema is OK.