# EVLog

Applicazione per la gestione e l'analisi dei viaggi e delle
ricariche di un veicolo elettrico.

L'applicazione è distribuita tramite **Docker**, quindi non è necessario
installare manualmente PHP, MySQL o Apache. Tutto viene avviato
automaticamente tramite container.

------------------------------------------------------------------------

# Requisiti

-   Linux (testato su **Ubuntu 24.04**)
-   Connessione Internet
-   Permessi sudo

------------------------------------------------------------------------

# 1. Aggiornare il sistema

Aprire il terminale ed eseguire:

``` bash
sudo apt update
sudo apt upgrade -y
```

------------------------------------------------------------------------

# 2. Installare Docker

Installare i pacchetti necessari:

``` bash
sudo apt install -y ca-certificates curl gnupg
```

Creare la directory per le chiavi Docker:

``` bash
sudo install -m 0755 -d /etc/apt/keyrings
```

Scaricare la chiave GPG ufficiale di Docker:

``` bash
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
```

Impostare i permessi:

``` bash
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```

Aggiungere il repository Docker:

``` bash
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

Aggiornare i repository:

``` bash
sudo apt update
```

Installare Docker e Docker Compose:

``` bash
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

------------------------------------------------------------------------

# 3. Verificare l'installazione

Controllare la versione:

``` bash
docker --version
```

Test rapido:

``` bash
sudo docker run hello-world
```

Se appare il messaggio **Hello from Docker**, l'installazione è
corretta.

------------------------------------------------------------------------

# 4. Usare Docker senza sudo (opzionale ma consigliato)

Aggiungere il proprio utente al gruppo docker:

``` bash
sudo usermod -aG docker $USER
```

Ricaricare il gruppo:

``` bash
newgrp docker
```

Ora è possibile usare Docker senza `sudo`.

------------------------------------------------------------------------

# 5. Copiare l'applicazione

Clonare il repository:

``` bash
git clone https://github.com/<username>/EVLog.git
```

Entrare nella directory del progetto:

``` bash
cd EVLog/evlog
```

La struttura del progetto dovrebbe essere simile a questa:

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

# 6. Avviare l'applicazione

All'interno della cartella del progetto eseguire:

``` bash
docker compose up --build -d
```

Docker farà automaticamente:

-   build del container PHP
-   avvio del database MariaDB
-   inizializzazione del database tramite `init.sql`
-   avvio di phpMyAdmin

------------------------------------------------------------------------

# 7. Verificare che i container siano attivi

``` bash
docker ps
```

Dovrebbero essere presenti tre container:

-   `volvo_trips_web`
-   `volvo_trips_db`
-   `volvo_trips_phpmyadmin`

------------------------------------------------------------------------

# 8. Accesso all'applicazione

Aprire il browser.

Applicazione EV Log:

    http://localhost:8080

phpMyAdmin:

    http://localhost:8081

Credenziali phpMyAdmin:

    server: db
    user: volvo_user
    password: volvo_password

------------------------------------------------------------------------

# 9. Visualizzare i log

Per controllare eventuali errori:

``` bash
docker compose logs -f
```

Solo container web:

``` bash
docker compose logs web
```

------------------------------------------------------------------------

# 10. Fermare l'applicazione

``` bash
docker compose down
```

------------------------------------------------------------------------

# 11. Reset completo del database

Se si desidera ricreare completamente il database:

``` bash
docker compose down -v
docker compose up --build
```

------------------------------------------------------------------------

# Note

Il database viene inizializzato automaticamente al primo avvio
utilizzando lo script:

    database/init.sql

I dati del database sono persistenti grazie ai **Docker volumes**.
