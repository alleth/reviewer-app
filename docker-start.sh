#!/bin/bash
set -e

# Fix MPM conflict at every startup before Apache launches
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf

a2enmod mpm_prefork rewrite 2>/dev/null || true

exec apache2-foreground
