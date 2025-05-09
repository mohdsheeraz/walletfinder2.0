# 1) Base image with PHP CLI
FROM php:8.2-cli

# 2) Install required PHP extensions: gmp and mysqli
RUN docker-php-ext-install gmp mysqli

# 3) Set working directory
WORKDIR /app

# 4) Copy all your files into the container
COPY . .

# 5) Ensure the CLI script is executable
RUN chmod +x wallet_scanner.php

# 6) Default command: run the scanner
CMD ["php", "wallet_scanner.php"]
