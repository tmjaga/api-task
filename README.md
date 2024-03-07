# Simple Demo API

Simple Demo API to get, post, update, delete operations over the DB table

## Requirements
- Docker
>If you are using Windows, please make sure to install Docker Desktop.
Next, you should ensure that Windows Subsystem for Linux 2 (WSL2) is installed and enabled.

- [Postman](https://www.postman.com) or other HTTP client for testing the API.

## Installation and run

#### 1. Clone the project
```bash
git clone https://github.com/tmjaga/api-task.git
```
#### 2. Navigate into the project folder using terminal
```bash
cd api-task
```
### 3. Start api-task docker container
In the project folder run:
```bash
docker compose up -d
```

## Usage

With Postman or other HTTP client for testing the API the following queries can be executed:
- GET http://localhost:8000/constructionStages get all record from construction_stages table
- GET http://localhost:8000/constructionStages/<record_id> get one record from construction_stages table
- POST http://localhost:8000/constructionStages insert new record in to the construction_stages table (body in JSON format must be provided)
- PATCH http://localhost:8000/constructionStages/<record_id> update record in the construction_stages table with data in JSON format (body in JSON format must be provided)
- DELETE http://localhost:8000/constructionStages/<record_id> Make a soft delete a record in construction_stages table
