#!/bin/sh
echo "Running cronjob on " "$(date +%D-%H:%M)"
curl api:8080/cronjob/
