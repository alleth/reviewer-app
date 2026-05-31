#!/bin/bash
set -e

# Fix MPM conflict — remove event/worker so only prefork is active
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork 2>/dev/null || true

# Enable mod_rewrite for CakePHP routing (separate from MPM so it never gets skipped)
a2enmod rewrite

exec apache2-foreground
