"""
Configuration file for Pico W connection to LevelUp Laravel backend
"""

# Default / Fallback Settings
# ===========================

# WiFi credentials (override in secrets.py)
WIFI_SSID = ""
WIFI_PASSWORD = ""

# Laravel API endpoint (override in secrets.py)
# Example: "http://194.143.1.110:8100/api/pico/display"
API_URL = ""
# Display settings
POLL_INTERVAL_SECONDS = 2
API_TIMEOUT_SECONDS = 3
DEFAULT_MESSAGE = "Welcome to LevelUp!"

# I2C pins for OLED display (SSD1306)
I2C_SDA_PIN = 4
I2C_SCL_PIN = 5
I2C_FREQ = 100000

# OLED display settings
OLED_WIDTH = 128
OLED_HEIGHT = 32
OLED_ADDR = 0x3C

# RGB LED settings (WS2812)
RGB_LED_PIN = 6
RGB_LED_COUNT = 1
SITTING_COLOR = (128, 0, 128)
STANDING_COLOR = (0, 128, 0)
OFF_COLOR = (0, 0, 0)

# Potentiometer settings
POT_PIN = 26
BRIGHTNESS_MIN = 0.0

# Button and pause LED settings
PAUSE_BUTTON_PIN = 10
PAUSE_LED_PIN = 7

# Import Local Secrets
# ====================
# This tries to import variables from secrets.py to override the defaults above.
# secrets.py should be in .gitignore and contain your real WiFi/API credentials.
try:
    from secrets import *
except ImportError:
    pass # No secrets file found, using defaults
