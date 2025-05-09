# Use a modern, supported version of PHP
FROM php:8.2-cli

# Copy project files
WORKDIR /app
COPY . .

# Expose port if needed (e.g., for built-in PHP server)
# EXPOSE 8000

# Default command
CMD [ "php", "wallet_scanner.php" ]
