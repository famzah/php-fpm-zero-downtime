# Achieving Zero-Downtime PHP-FPM Restarts and Atomic Updates
PHP-FPM (FastCGI Process Manager) is a powerful solution for managing PHP processes, but it poses challenges when updating PHP applications or configurations without impacting active requests. If a slow child lingers, incoming requests queue up, causing delays for the duration of the timeout. This delay can lead to significant downtime, especially for high-traffic applications. You can read more detailed info at the [blog page](https://blog.famzah.net/2024/12/20/achieving-zero-downtime-php-fpm-restarts-and-atomic-updates/).

# Setup
First clone this repository to a local directory. If you want to start ASAP, then clone to the same location as mine - ```/home/famzah/php-fpm-graceful```. It doesn't matter if the real UNIX user is "famzah".

The sample redundant pool is named ```www```. If you want to manage more than one redundant pool, copy all files to another directory and adapt all configuration files accordingly. A second balancer pool must also be added to the Apache configuration. Using a ```grep``` search for ```www``` and ```famzah``` across the files is a practical way to identify what needs to be updated.

All commands must be executed from the parent installation directory. For instance, if the repository is cloned to ```/home/myuser```, navigate to this directory first using ```cd /home/myuser``` before executing commands like ```bin/pools-manager```.

A few absolute (full) paths must be modified for your local installation in the following files:
- ```etc/php/fpm/redundant-pools/www/pools-manager.toml```
- ```etc/apache2/sites-enabled/82-php-fpm.conf```
- the symlink target for ```www/current.release```

To activate the Apache configuration, create a symlink for ```etc/apache2/sites-enabled/82-php-fpm.conf``` in ```/etc/apache2/sites-enabled/``` and then restart Apache. For example:
```Bash
ln -s /home/myuser/php-fpm-graceful/etc/apache2/sites-enabled/82-php-fpm.conf /etc/apache2/sites-enabled/82-php-fpm.conf
sudo systemctl restart apache2
```

# Management
Here are some sample commands:
```Bash
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager -h
usage: pools-manager [-h] {start,stop,restart,status,list} ...

Service Management Tool

options:
  -h, --help            show this help message and exit

Commands:
  {start,stop,restart,status,list}
    start               Start all pools, one whole pool or just one replica of a pool
    stop                Stop all pools, one whole pool or just one replica of a pool
    restart             Restart all pools, one whole pool or just one replica of a pool
    status              Show status for all pools, one whole pool or just one replica of a pool
    list                List all pools
	
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager list
www -> (replica_count="2" backend="apache" balancer_name="phpfpmlb" manager_url="http://localhost:82/balancer-manager" member_urls="['fcgi://pool-1', 'fcgi://pool-2']")

famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager list -q
www

famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager status
Redundant pool "www"...
  Status for replica 1 [OK]  (LB="Init Ok", ping_php="pong", current="/home/famzah/php-fpm-graceful/www/release2")
  Status for replica 2 [OK]  (LB="Init Ok", ping_php="pong", current="/home/famzah/php-fpm-graceful/www/release2")
  
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager status -q
Redundant pool "www"...
  Status for replica 1 [OK]
  Status for replica 2 [OK]
  
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager stop single www 2
Redundant pool "www"...
  Stopping replica 2 . [OK]
  
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager start single www 2
Redundant pool "www"...
  Starting replica 2  [OK]
  
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart single www 2
Redundant pool "www"...
  Deactivating the load balancer for replica 2  [OK]
  Stopping replica 2 . [OK]
  Starting replica 2  [OK]
  Activating the load balancer for replica 2  [OK]
```

# Atomic change of the release and zero-downtime restart
Here are some sample commands:
```Bash
famzah@vbox64:~/php-fpm-graceful/www$ ln -nsf /home/famzah/php-fpm-graceful/www/release1 current.release

famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart
Redundant pool "www"...
  Deactivating the load balancer for replica 1  [OK]
  Stopping replica 1 . [OK]
  Starting replica 1  [OK]
  Activating the load balancer for replica 1  [OK]
  Deactivating the load balancer for replica 2  [OK]
  Stopping replica 2 . [OK]
  Starting replica 2  [OK]
  Activating the load balancer for replica 2  [OK]

famzah@vbox64:~$ curl -sS -m 2 http://localhost:82/ping.php
pong1

#
# Change the release directory to a new one
#
famzah@vbox64:~/php-fpm-graceful/www$ ln -nsf /home/famzah/php-fpm-graceful/www/release2 current.release

# XXX: Apache starts to serve the static files regardless if PHP-FPM was restarted
famzah@vbox64:~$ curl -sS -m 2 http://localhost:82/release.txt
2

# PHP-FPM needs a restart to start using the new release directory
famzah@vbox64:~$ curl -sS -m 2 http://localhost:82/ping.php
pong1

famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart
...

# PHP-FPM uses the new release directory
famzah@vbox64:~$ curl -sS -m 2 http://localhost:82/ping.php
pong2
```

**Stress tests**:
```bash
famzah@vbox64:~/php-fpm-graceful$ bin/ping-stress 
.........................[and so on]

famzah@vbox64:~/php-fpm-graceful$ bin/sleep-stress 
.............[and so on]

famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart
Redundant pool "www"...
  Deactivating the load balancer for replica 1 .. [OK]
  Stopping replica 1 . [OK]
  Starting replica 1  [OK]
  Activating the load balancer for replica 1  [OK]
  Deactivating the load balancer for replica 2 .. [OK]
  Stopping replica 2 . [OK]
  Starting replica 2  [OK]
  Activating the load balancer for replica 2  [OK]

# do many restarts one after each other
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart
...
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart

# change the release directory
famzah@vbox64:~/php-fpm-graceful/www$ ln -nsf /home/famzah/php-fpm-graceful/www/release1 current.release

# do many restarts one after each other
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart
...
famzah@vbox64:~/php-fpm-graceful$ bin/pools-manager restart

# no stress tests fail and PHP-FPM restarts are fully atomic and with zero downtime
```

# Description of the files
```Bash
# the main "bin/pools-manager" tool and other helper commands
./bin/fcgi-request
./bin/balancer-manager
./bin/sleep-stress
./bin/pools-manager
./bin/ping-stress
./bin/ps-list

# Common main PHP-FPM config for all pools.
# It's being symlinked from each pool.
# If a pool needs its own config, you can copy the file instead of using a symlink.
./etc/php/fpm/redundant-pools/php-fpm.conf

# config files for the "www" pool and its two replicas "1.conf" and "2.conf"
./etc/php/fpm/redundant-pools/www/php-fpm.conf
./etc/php/fpm/redundant-pools/www/pool.conf
./etc/php/fpm/redundant-pools/www/1.conf
./etc/php/fpm/redundant-pools/www/2.conf
./etc/php/fpm/redundant-pools/www/pools-manager.toml

# Apache web server config
./etc/apache2/sites-enabled/82-php-fpm.conf

# library files
./lib/unshare-wrapper

# running PHP-FPM socket and PID files
./sockets/php-fpm-www1.sock
./sockets/php-fpm-www2.sock
./pid/php-fpm-www1.pid
./pid/php-fpm-www2.pid

# a symlink which points to the "current" release
./www/current.release -> www/release1

# example source code for the demo, "release1"
./www/release1/1.txt
./www/release1/sleep-long.php
./www/release1/release.txt
./www/release1/ping.php

# example source code for the demo, "release2"
./www/release2/2.txt
./www/release2/sleep-long.php
./www/release2/release.txt
./www/release2/ping.php
```
