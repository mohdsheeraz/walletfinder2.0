# 1) Base image with PHP CLI
FROM php:8.2-cli

# 2) Install system dependencies for GMP and MySQLi
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      libgmp-dev \
      default-mysql-client \
      default-libmysqlclient-dev && \
    rm -rf /var/lib/apt/lists/*

# 3) Compile & enable the PHP extensions
RUN docker-php-ext-install gmp mysqli

# 4) Set working directory
WORKDIR /app

# 5) Copy project files
COPY . /app

# 6) Make the scanner executable
RUN chmod +x wallet_scanner.php

# 7) Default command
CMD ["php", "wallet_scanner.php"]
