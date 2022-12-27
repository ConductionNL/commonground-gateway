#!/bin/sh
echo "Running cronjob on " "$(date +%D-%H:%M)"
curl api/cronjob
