# EVLog

EVLog is a PHP + MySQL application for tracking and analyzing trips and
charging sessions of an electric vehicle.

**Table of Contents**
- [Introduction](#evlog)
- [Requirements](#requirements)
- [1. Update the system](#1-update-the-system)
- [2. Install Docker](#2-install-docker)
- [3. Verify the installation](#3-verify-the-installation)
- [4. Use Docker without sudo (optional but recommended)](#4-use-docker-without-sudo-optional-but-recommended)
- [5. Clone the application](#5-clone-the-application)
- [6. Start the application](#6-start-the-application)
- [7. Verify the containers](#7-verify-the-containers)
- [8. Access the application](#8-access-the-application)
- [9. View logs](#9-view-logs)
- [10. Stop the application](#10-stop-the-application)
- [11. Reset the database](#11-reset-the-database)
- [Notes](#notes)

EVLog is an application that allows you to export trips from the official Volvo app in CSV format and import them into this system. This enables you to maintain a historical record and perform analysis and statistics on your trips.

Below are some screenshots of the interface:

![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/1.png)
![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/2.png)
![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/3.png)
![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/4.png)
![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/5.png)
![Screenshot EVLog](https://raw.githubusercontent.com/HostileCoding/EVLog/main/evlog/screenshots/6.png)

> **Disclaimer:** This application is provided "as is" and may contain errors or inaccuracies. The developer assumes no responsibility for any use or misuse of the application.

------------------------------------------------------------------------

# Requirements

-   Linux (tested on **Ubuntu 24.04**)
-   Internet connection
-   sudo privileges

------------------------------------------------------------------------

# 1. Update the system

Open a terminal and run:

``` bash
sudo apt update
sudo apt upgrade -y
```

------------------------------------------------------------------------

# 2. Install Docker

Install required packages:

``` bash
sudo apt install -y ca-certificates curl gnupg
```

Create the Docker keyring directory:

``` bash
sudo install -m 0755 -d /etc/apt/keyrings
```

Download the official Docker GPG key:

``` bash
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
```

Set permissions:

``` bash
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```

Add the Docker repository:

``` bash
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

Update package lists:

``` bash
sudo apt update
```

Install Docker and Docker Compose:

``` bash
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

------------------------------------------------------------------------

# 3. Verify the installation

Check the installed Docker version:

``` bash
docker --version
```

Run a quick test:

``` bash
sudo docker run hello-world
```

If you see **Hello from Docker**, the installation is successful.

------------------------------------------------------------------------

# 4. Use Docker without sudo (optional but recommended)

Add your user to the Docker group:

``` bash
sudo usermod -aG docker $USER
```

Reload the group:

``` bash
newgrp docker
```

Now you can run Docker commands without `sudo`.

------------------------------------------------------------------------

# 5. Clone the application

Clone the repository:

``` bash
git clone https://github.com/HostileCoding/EVLog.git
```

Enter the project directory:

``` bash
cd EVLog/evlog
```

The project structure should look like this:

    EVLog
    │
    ├── docker-compose.yml
    ├── Dockerfile
    │
    ├── src
    │   ├── index.php
    │   ├── config.php
    │   └── ...
    │
    └── database
        └── init.sql

------------------------------------------------------------------------

# 6. Start the application

Inside the project directory run:

``` bash
docker compose up --build -d
```

Docker will automatically:

-   build the PHP container
-   start the MariaDB database
-   initialize the database using `init.sql`
-   start phpMyAdmin

------------------------------------------------------------------------

# 7. Verify the containers

``` bash
docker ps
```

You should see three containers:

-   `volvo_trips_web`
-   `volvo_trips_db`
-   `volvo_trips_phpmyadmin`

------------------------------------------------------------------------

# 8. Access the application

Open your browser.

EV Log application:

    http://localhost:8080

phpMyAdmin:

    http://localhost:8081

phpMyAdmin credentials:

    server: db
    user: volvo_user
    password: volvo_password

------------------------------------------------------------------------

# 9. View logs

To monitor logs and troubleshoot:

``` bash
docker compose logs -f
```

Only the web container:

``` bash
docker compose logs web
```

------------------------------------------------------------------------

# 10. Stop the application

``` bash
docker compose down
```

------------------------------------------------------------------------

# 11. Reset the database

To completely recreate the database:

``` bash
docker compose down -v
docker compose up --build
```

------------------------------------------------------------------------

# Notes

The database is automatically initialized during the first startup
using:

    database/init.sql

Database data is persistent thanks to **Docker volumes**.
