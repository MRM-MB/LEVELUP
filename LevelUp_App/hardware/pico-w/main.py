"""
LevelUp Pico W Display Client
Connects to phone hotspot, fetches current user from Laravel backend,
and displays personalized greeting on OLED screen.
"""

# === Imports ===
import network # type: ignore
import urequests # type: ignore
import json
import time
import framebuf # type: ignore
from machine import Pin, SoftI2C, ADC # type: ignore
from ssd1306 import SSD1306_I2C
from config import (
    WIFI_SSID, WIFI_PASSWORD, API_URL,
    POLL_INTERVAL_SECONDS, API_TIMEOUT_SECONDS, DEFAULT_MESSAGE,
    I2C_SDA_PIN, I2C_SCL_PIN, I2C_FREQ,
    OLED_WIDTH, OLED_HEIGHT, OLED_ADDR,
    RGB_LED_PIN, RGB_LED_COUNT,
    SITTING_COLOR, STANDING_COLOR, OFF_COLOR,
    POT_PIN, BRIGHTNESS_MIN,
    PAUSE_BUTTON_PIN, PAUSE_LED_PIN
)

# === Optional Modules ===
# Try to import neopixel (might not be available on all boards)
try:
    import neopixel # type: ignore
    NEOPIXEL_AVAILABLE = True
except ImportError:
    NEOPIXEL_AVAILABLE = False
    print("Warning: neopixel module not available")

# === Global State ===
# Cache file for offline fallback
CACHE_FILE = "user_cache.json"

# Global variables
oled = None
last_displayed_message = None
wlan = None
rgb_led = None
potentiometer = None
last_led_phase = None
current_led_color = OFF_COLOR  # Track current LED base color
last_brightness = None  # Track last brightness value
in_warning_mode = False  # Track if we're in warning mode
pause_button = None  # Pause button on GP10
pause_led = None  # Pause indicator LED on GP7
is_paused = False  # Track pause state
last_button_time = 0  # For button debouncing
last_button_state = 1  # Track previous button state (1 = not pressed)
warning_animation_frame = 0  # Track animation frame for warning screens

# === Block Font Glyphs ===
# Minimal block font for bold OLED headings (5x7 base grid)
BLOCK_FONT_WIDTH = 5
BLOCK_FONT_HEIGHT = 7
BLOCK_FONT_SPACING = 1
BLOCK_FONT = {
    "L": (
        "1....",
        "1....",
        "1....",
        "1....",
        "1....",
        "1....",
        "11111",
    ),
    "E": (
        "11111",
        "1....",
        "1....",
        "11111",
        "1....",
        "1....",
        "11111",
    ),
    "V": (
        "1...1",
        "1...1",
        "1...1",
        "1...1",
        "1...1",
        ".1.1.",
        "..1..",
    ),
    "U": (
        "1...1",
        "1...1",
        "1...1",
        "1...1",
        "1...1",
        "1...1",
        "11111",
    ),
    "P": (
        "11110",
        "1...1",
        "1...1",
        "11110",
        "1....",
        "1....",
        "1....",
    ),
    "!": (
        "..1..",
        "..1..",
        "..1..",
        "..1..",
        "..1..",
        ".....",
        "..1..",
    ),
}


# === Logging Utilities ===
def log(message):
    """Print timestamped log message"""
    print(f"[{time.time()}] {message}")


# === WiFi Management ===
def connect_wifi():
    """Connect to WiFi hotspot"""
    global wlan
    
    log(f"Connecting to WiFi: {WIFI_SSID}")
    wlan = network.WLAN(network.STA_IF)
    wlan.active(True)
    
    if wlan.isconnected():
        log("Already connected to WiFi")
        return True
    
    # Start connection
    wlan.connect(WIFI_SSID, WIFI_PASSWORD)
    
    # Wait for connection
    max_wait = 30
    while max_wait > 0:
        status = wlan.status()
        
        if status == 3:  # Connected
            ip = wlan.ifconfig()[0]
            log(f"WiFi connected! IP: {ip}")
            return True
        elif status < 0:  # Error
            log(f"WiFi connection failed with status: {status}")
            return False
        
        max_wait -= 1
        time.sleep(1)
    
    log("WiFi connection timeout")
    return False


def check_wifi():
    """Check if WiFi is still connected, reconnect if needed"""
    global wlan
    
    if not wlan or not wlan.isconnected():
        log("WiFi disconnected, reconnecting...")
        return connect_wifi()
    
    return True


# === Cache Handling ===
def load_cached_user():
    """Load user data from cache file"""
    try:
        with open(CACHE_FILE, "r") as f:
            data = json.load(f)
            log(f"Loaded cached user: {data.get('username', 'Unknown')}")
            return data
    except Exception as e:
        log(f"Failed to load cache: {e}")
        return None


def save_cached_user(data):
    """Save user data to cache file"""
    try:
        with open(CACHE_FILE, "w") as f:
            json.dump(data, f)
        log("User data cached successfully")
    except Exception as e:
        log(f"Failed to save cache: {e}")


# === Backend Fetching ===
def fetch_user_data():
    """Fetch current user data from Laravel API"""
    try:
        log(f"Fetching user data from {API_URL}")
        response = urequests.get(API_URL, timeout=API_TIMEOUT_SECONDS)
        
        if response.status_code == 200:
            data = response.json()
            log(f"API response: {data}")
            
            # Save to cache for offline fallback
            save_cached_user(data)
            
            response.close()
            return data
        else:
            log(f"API error: HTTP {response.status_code}")
            response.close()
            return None
            
    except OSError as e:
        log(f"Network error: {e} - Check WiFi connection")
        return None
    except Exception as e:
        log(f"API request failed: {e}")
        return None


def get_display_data():
    """Get user data from API or cache"""
    
    # Try to fetch from API first
    if check_wifi():
        data = fetch_user_data()
        if data:
            return data
    
    # Fallback to cache
    log("Using cached data...")
    cached = load_cached_user()
    if cached:
        return cached
    
    # Ultimate fallback
    return {
        'message': DEFAULT_MESSAGE,
        'username': None,
        'logged_in': False
    }


# === Text Layout Helpers ===
def wrap_text(text, width=16):
    """
    Break text into lines that fit the OLED display
    width: characters per line (approximate, depends on font)
    """
    words = text.split()
    lines = []
    current_line = ""
    
    for word in words:
        test_line = f"{current_line} {word}".strip()
        if len(test_line) <= width:
            current_line = test_line
        else:
            if current_line:
                lines.append(current_line)
            current_line = word
    
    if current_line:
        lines.append(current_line)
    
    return lines if lines else [text[:width]]


def center_text(text, width=16):
    """Center text within the given width"""
    if len(text) >= width:
        return 0
    return (width * 8 - len(text) * 8) // 2  # 8 pixels per character


# === Display Rendering Helpers ===
def draw_scaled_text(oled_display, text, x, y, scale=1):
    """Render text with optional scaling factor on the OLED display."""
    if scale <= 1:
        oled_display.text(text, x, y)
        return

    width = len(text) * 8
    if width == 0:
        return

    buffer = bytearray(width * 8 // 8)
    temp_fb = framebuf.FrameBuffer(buffer, width, 8, framebuf.MONO_VLSB)
    temp_fb.fill(0)
    temp_fb.text(text, 0, 0, 1)

    for src_y in range(8):
        for src_x in range(width):
            if temp_fb.pixel(src_x, src_y):
                oled_display.fill_rect(
                    x + src_x * scale,
                    y + src_y * scale,
                    scale,
                    scale,
                    1,
                )


def draw_centered_scaled_text(oled_display, text, y, scale=1):
    """Draw scaled text centered horizontally."""
    if scale <= 1:
        x_pos = center_text(text, OLED_WIDTH // 8)
        oled_display.text(text, x_pos, y)
        return

    text_width = len(text) * 8 * scale
    x_pos = max(0, (OLED_WIDTH - text_width) // 2)
    draw_scaled_text(oled_display, text, x_pos, y, scale)


def get_block_text_dimensions(text, scale=1, letter_spacing=None):
    """Compute pixel width/height for block font text."""
    if scale <= 0:
        scale = 1
    spacing = BLOCK_FONT_SPACING if letter_spacing is None else letter_spacing
    width = 0
    length = len(text)
    for index, char in enumerate(text):
        glyph = BLOCK_FONT.get(char.upper())
        if glyph:
            width += BLOCK_FONT_WIDTH * scale
        else:
            width += 5 * scale
        if index < length - 1:
            width += spacing * scale
    height = BLOCK_FONT_HEIGHT * scale
    return width, height


def draw_block_text(oled_display, text, x, y, scale=1, letter_spacing=None):
    """Render text using the custom block font for bold headings."""
    if scale <= 0:
        scale = 1
    spacing = BLOCK_FONT_SPACING if letter_spacing is None else letter_spacing
    cursor_x = x
    for index, char in enumerate(text):
        glyph = BLOCK_FONT.get(char.upper())
        if not glyph:
            draw_scaled_text(oled_display, char, cursor_x, y, scale)
            cursor_x += 6 * scale
        else:
            for row, pattern in enumerate(glyph):
                for col, pixel in enumerate(pattern):
                    if pixel == "1":
                        oled_display.fill_rect(
                            cursor_x + col * scale,
                            y + row * scale,
                            scale,
                            scale,
                            1,
                        )
            cursor_x += BLOCK_FONT_WIDTH * scale
        if index < len(text) - 1:
            cursor_x += spacing * scale


def draw_block_logo(oled_display, text, x, y, scale=1, outline=1, letter_spacing=None):
    """Draw block text with an optional outline for a logo-style look."""
    if scale <= 0:
        scale = 1

    if outline and outline > 0:
        width, height = get_block_text_dimensions(text, scale, letter_spacing=letter_spacing)
        stroke_offsets = []
        for dx in range(-outline, outline + 1):
            for dy in range(-outline, outline + 1):
                if dx == 0 and dy == 0:
                    continue
                if abs(dx) + abs(dy) != outline:
                    continue
                if x + dx < 0 or y + dy < 0:
                    continue
                if x + dx + width > OLED_WIDTH or y + dy + height > OLED_HEIGHT:
                    continue
                stroke_offsets.append((dx, dy))

        for dx, dy in stroke_offsets:
            draw_block_text(oled_display, text, x + dx, y + dy, scale, letter_spacing=letter_spacing)

    draw_block_text(oled_display, text, x, y, scale, letter_spacing=letter_spacing)


def _safe_pixel(oled_display, x, y):
    if 0 <= x < OLED_WIDTH and 0 <= y < OLED_HEIGHT:
        oled_display.pixel(x, y, 1)


def draw_corner_icons(oled_display, margin=2, size=2):
    """Render small corner icons for subtle decoration."""
    size = max(1, size)
    corners = [
        (margin, margin),
        (OLED_WIDTH - margin - 1, margin),
        (margin, OLED_HEIGHT - margin - 1),
        (OLED_WIDTH - margin - 1, OLED_HEIGHT - margin - 1),
    ]

    for center_x, center_y in corners:
        _safe_pixel(oled_display, center_x, center_y)
        for offset in range(1, size + 1):
            _safe_pixel(oled_display, center_x + offset, center_y)
            _safe_pixel(oled_display, center_x - offset, center_y)
            _safe_pixel(oled_display, center_x, center_y + offset)
            _safe_pixel(oled_display, center_x, center_y - offset)


# === Warning Display Helpers ===
WARNING_ANIMATION_CYCLE = 2


def draw_warning_border(oled_display, frame):
    """Blink a simple rectangular border to draw attention."""
    if frame % 2 == 0:
        oled_display.rect(0, 0, OLED_WIDTH, OLED_HEIGHT, 1)


def draw_warning_screen(oled_display, action_text, time_remaining, frame):
    """Render the animated warning screen prompting the next action."""
    draw_warning_border(oled_display, frame)

    header_text = "Get Ready To"
    action_text = action_text.upper()

    header_scale = 1
    action_scale = 2 if OLED_HEIGHT >= 48 and len(action_text) * 16 <= OLED_WIDTH else 1

    header_height = 8 * header_scale
    action_height = 8 * action_scale
    gap = 4 if action_scale > 1 else 2
    total_height = header_height + gap + action_height
    top = max(0, (OLED_HEIGHT - total_height) // 2)

    draw_centered_scaled_text(oled_display, header_text, top, scale=header_scale)
    draw_centered_scaled_text(oled_display, action_text, top + header_height + gap, scale=action_scale)


# === Hardware Initialization ===
def init_rgb_led():
    """Initialize RGB LED (WS2812) on GP6"""
    global rgb_led
    
    if not NEOPIXEL_AVAILABLE:
        log("Neopixel module not available, RGB LED disabled")
        return False
    
    try:
        log(f"Initializing RGB LED on GP{RGB_LED_PIN}...")
        rgb_led = neopixel.NeoPixel(Pin(RGB_LED_PIN, Pin.OUT), RGB_LED_COUNT)
        rgb_led[0] = OFF_COLOR
        rgb_led.write()
        log("RGB LED initialized")
        return True
    except Exception as e:
        log(f"Failed to initialize RGB LED: {e}")
        return False


def init_potentiometer():
    """Initialize potentiometer for brightness control"""
    global potentiometer
    
    try:
        log(f"Initializing potentiometer on GP{POT_PIN}...")
        potentiometer = ADC(POT_PIN)
        log("Potentiometer initialized")
        return True
    except Exception as e:
        log(f"Failed to initialize potentiometer: {e}")
        return False


def init_pause_button():
    """Initialize pause button on GP10"""
    global pause_button
    
    try:
        log(f"Initializing pause button on GP{PAUSE_BUTTON_PIN}...")
        pause_button = Pin(PAUSE_BUTTON_PIN, Pin.IN, Pin.PULL_UP)
        log("Pause button initialized")
        return True
    except Exception as e:
        log(f"Failed to initialize pause button: {e}")
        return False


def init_pause_led():
    """Initialize pause indicator LED on GP7"""
    global pause_led
    
    try:
        log(f"Initializing pause LED on GP{PAUSE_LED_PIN}...")
        pause_led = Pin(PAUSE_LED_PIN, Pin.OUT)
        pause_led.value(0)  # Start with LED off
        log("Pause LED initialized")
        return True
    except Exception as e:
        log(f"Failed to initialize pause LED: {e}")
        return False


# === RGB LED Control ===
def read_brightness():
    """Read brightness level from potentiometer (0.0 to 1.0)"""
    if not potentiometer:
        return 1.0  # Full brightness if no potentiometer
    
    try:
        # Read ADC value (0-65535)
        raw_value = potentiometer.read_u16()
        # Convert to 0.0-1.0 range
        brightness = raw_value / 65535.0
        # Apply minimum brightness
        brightness = max(0.0, brightness)
        return brightness
    except Exception as e:
        log(f"Error reading potentiometer: {e}")
        return 1.0
        return brightness
    except Exception as e:
        log(f"Error reading potentiometer: {e}")
        return 1.0


def apply_brightness(color, brightness):
    """Apply brightness level to RGB color"""
    return tuple(int(c * brightness) for c in color)


def update_rgb_led(phase, time_remaining=None):
    """Update RGB LED based on timer phase"""
    global last_led_phase, current_led_color, in_warning_mode
    
    log(f"update_rgb_led called with phase: {phase}, time_remaining: {time_remaining}, last_phase: {last_led_phase}")
    
    if not rgb_led:
        log("RGB LED not initialized, skipping update")
        return
    
    # Determine base color based on phase and time remaining
    base_color = None
    
    # Check if we're in warning mode (≤30 seconds)
    is_warning = time_remaining is not None and time_remaining <= 30 and time_remaining > 0 and phase
    
    # Show action color if in warning mode
    if is_warning:
        in_warning_mode = True
        if phase == 'sitting':
            # "Get ready to stand up!" - show GREEN (standing color)
            base_color = STANDING_COLOR
            log(f"WARNING: {time_remaining}s remaining - Get ready to STAND UP (green)")
        elif phase == 'standing':
            # "Get ready to sit down!" - show PURPLE (sitting color)
            base_color = SITTING_COLOR
            log(f"WARNING: {time_remaining}s remaining - Get ready to SIT DOWN (purple)")
        
        current_led_color = base_color
        last_led_phase = 'warning_' + phase
    elif phase != last_led_phase or (in_warning_mode and not is_warning):
        # Normal phase change or exiting warning mode
        in_warning_mode = False
        if phase == 'sitting':
            base_color = SITTING_COLOR
            log(f"Setting LED to SITTING (purple)")
        elif phase == 'standing':
            base_color = STANDING_COLOR
            log(f"Setting LED to STANDING (green)")
        else:
            base_color = OFF_COLOR
            log(f"Setting LED to OFF (phase={phase})")
        
        current_led_color = base_color
        last_led_phase = phase
    
    # Apply brightness and update LED if color changed
    if base_color is not None:
        brightness = read_brightness()
        color = apply_brightness(current_led_color, brightness)
        rgb_led[0] = color
        rgb_led.write()
        log(f"LED updated - brightness: {brightness:.2f}, color: {color}")


def update_led_brightness():
    """Update LED brightness based on potentiometer (called frequently)"""
    global last_brightness
    
    if not rgb_led or current_led_color == OFF_COLOR:
        return
    
    brightness = read_brightness()
    
    # Only update if brightness changed significantly (avoid flicker)
    if last_brightness is None or abs(brightness - last_brightness) > 0.05:
        color = apply_brightness(current_led_color, brightness)
        rgb_led[0] = color
        rgb_led.write()
        last_brightness = brightness


# === Pause Controls ===
def check_pause_button():
    """Check if pause button is pressed and toggle pause state (edge-triggered)"""
    global is_paused, last_button_time, last_button_state
    
    if not pause_button or not pause_led:
        return False
    
    # Read button (active low - pressed = 0)
    button_state = pause_button.value()
    current_time = time.time()
    
    # Edge detection: trigger only on button press (transition from 1 to 0)
    if button_state == 0 and last_button_state == 1:
        # Debounce: only register press if 0.5 seconds have passed since last press
        if (current_time - last_button_time) > 0.5:
            last_button_time = current_time
            
            # Toggle pause state
            is_paused = not is_paused
            
            # Update pause LED
            pause_led.value(1 if is_paused else 0)
            
            # Send pause/resume command to backend
            toggle_timer_pause(is_paused)
            
            log(f"Button pressed - Timer {'PAUSED' if is_paused else 'RESUMED'}")
            
            last_button_state = button_state
            return True
    
    # Update last button state
    last_button_state = button_state
    return False


# === Backend Commands ===
def toggle_timer_pause(pause):
    """Send pause/resume command to backend API"""
    try:
        url = API_URL.replace('/display', '/timer-pause')
        # Convert Python bool to JSON bool (lowercase true/false)
        payload = json.dumps({'paused': bool(pause)})
        
        log(f"Sending {'pause' if pause else 'resume'} request to {url}")
        log(f"Payload: {payload}")
        
        response = urequests.post(
            url,
            data=payload,
            headers={'Content-Type': 'application/json'},
            timeout=API_TIMEOUT_SECONDS
        )
        
        if response.status_code == 200:
            data = response.json()
            log(f"Timer {'paused' if pause else 'resumed'} successfully: {data}")
        else:
            log(f"Failed to toggle pause: HTTP {response.status_code}")
        
        response.close()
        
    except Exception as e:
        log(f"Error toggling pause: {e}")


# === Main Display Logic ===
def display_message(data):
    """Display message on OLED screen"""
    global last_displayed_message, in_warning_mode, warning_animation_frame, is_paused
    
    if not oled:
        log("OLED not initialized")
        return
    
    # Extract data
    name = data.get('name')
    logged_in = data.get('logged_in', False)
    timer_phase = data.get('timer_phase')
    time_remaining = data.get('time_remaining')
    warning_message = data.get('warning_message')
    
    # Sync local pause state with server state if not recently toggled locally
    server_paused = data.get('is_paused', False)
    # Only update local state from server if we haven't toggled it locally recently (debounce)
    if time.time() - last_button_time > 2.0:
        if server_paused != is_paused:
            is_paused = server_paused
            if pause_led:
                pause_led.value(1 if is_paused else 0)
            log(f"Synced pause state from server: {is_paused}")
    
    # Update RGB LED based on timer phase and time remaining
    update_rgb_led(timer_phase, time_remaining)
    
    # Determine what message to display
    # Priority: warning_message > greeting (but keep warning if still in warning mode)
    if warning_message or (in_warning_mode and time_remaining is not None and time_remaining <= 30):
        if warning_message:
            display_text = warning_message
        else:
            if timer_phase == 'sitting':
                display_text = 'Get ready to stand up!'
            else:
                display_text = 'Get ready to sit down!'
    elif name and logged_in:
        # Show personalized greeting
        display_text = f"Hello, {name}!"
    else:
        # Show default message
        display_text = data.get('message', DEFAULT_MESSAGE)
    
    # Don't update if message hasn't changed
    # Include pause state in the signature to detect changes
    current_signature = f"{display_text}|{is_paused}"
    
    if current_signature == last_displayed_message and not display_text.lower().startswith('get ready'):
        return
    
    log(f"Displaying: {display_text} (Paused: {is_paused})")
    is_warning_display = display_text.lower().startswith('get ready')
    if not is_warning_display:
        warning_animation_frame = 0
    
    try:
        # Clear display
        oled.fill(0)
        
        # Special handling for "Welcome to LevelUp!" with logo treatment
        if display_text == "Welcome to LevelUp!":
            logo_text = "LEVELUP!"
            footnote_text = "by Group 3"
            block_scale = 2 if OLED_HEIGHT >= 32 else 1
            letter_spacing = 2 if block_scale >= 2 else 1

            logo_width, logo_height = get_block_text_dimensions(logo_text, block_scale, letter_spacing=letter_spacing)
            footnote_height = 8
            spacing = 3 if OLED_HEIGHT >= 32 else 1
            total_height = logo_height + spacing + footnote_height
            top_padding = max(0, (OLED_HEIGHT - total_height) // 2)

            logo_y = top_padding
            footnote_y = min(OLED_HEIGHT - footnote_height, logo_y + logo_height + spacing)
            logo_x = max(0, (OLED_WIDTH - logo_width) // 2)

            draw_corner_icons(oled, margin=1 if OLED_HEIGHT < 32 else 2, size=1)

            tagline = "Welcome to"
            if logo_y >= 10:
                tagline_x = center_text(tagline, OLED_WIDTH // 8)
                oled.text(tagline, tagline_x, logo_y - 10)

            outline = 1 if block_scale >= 2 else 0
            draw_block_logo(
                oled,
                logo_text,
                logo_x,
                logo_y,
                scale=block_scale,
                outline=outline,
                letter_spacing=letter_spacing,
            )

            footnote_x = center_text(footnote_text, OLED_WIDTH // 8)
            oled.text(footnote_text, footnote_x, footnote_y)
        elif display_text.startswith("Hello, ") and display_text.endswith("!"):
            name_text = display_text[7:-1].strip()

            if name_text:
                points_value = data.get('points')
                points_line = None
                
                # Determine secondary line content (Points or Paused)
                # When paused: show "PAUSED" instead of points
                # When not paused: show points
                if is_paused:
                    points_line = "PAUSED"
                elif points_value is not None:
                    if isinstance(points_value, float) and points_value.is_integer():
                        points_display = str(int(points_value))
                    else:
                        points_display = str(points_value)
                    points_line = "Points: {}".format(points_display)

                max_scale = 2 if OLED_HEIGHT >= 32 else 1
                scale = max_scale

                while scale > 1:
                    name_height_candidate = 8 * scale
                    points_height = 8 if points_line else 0
                    required_height = 8 + name_height_candidate + points_height
                    if required_height <= OLED_HEIGHT and len(name_text) * 8 * scale <= OLED_WIDTH:
                        break
                    scale -= 1

                if len(name_text) * 8 * scale > OLED_WIDTH:
                    scale = 1

                if scale > 1:
                    name_display = name_text.upper()
                else:
                    name_display = name_text

                if len(name_display) * 8 * scale <= OLED_WIDTH:
                    greeting_height = 8
                    name_height = 8 * scale
                    points_height = 8 if points_line else 0

                    gap1 = 1
                    gap2 = 1 if points_line else 0

                    while (
                        greeting_height
                        + gap1
                        + name_height
                        + gap2
                        + points_height
                        > OLED_HEIGHT
                    ):
                        if gap2 > 0:
                            gap2 -= 1
                        elif gap1 > 0:
                            gap1 -= 1
                        else:
                            break

                    total_height = (
                        greeting_height
                        + gap1
                        + name_height
                        + gap2
                        + points_height
                    )

                    available_extra = max(0, OLED_HEIGHT - total_height)
                    top_padding = available_extra // 2

                    greeting_y = top_padding
                    name_y = greeting_y + greeting_height + gap1

                    greeting_text = "Hello"
                    draw_centered_scaled_text(oled, greeting_text, greeting_y, scale=1)
                    draw_centered_scaled_text(oled, name_display, name_y, scale=scale)

                    if points_line:
                        points_y = name_y + name_height + gap2
                        if points_y + 8 > OLED_HEIGHT:
                            points_y = OLED_HEIGHT - 8
                        draw_centered_scaled_text(oled, points_line, points_y, scale=1)
                else:
                    lines = wrap_text(display_text, width=16)
                    max_lines = 3 if OLED_HEIGHT == 32 else 7
                    if points_line and max_lines > 1:
                        max_lines -= 1
                    lines = lines[:max_lines]
                    line_height = 10
                    total_height = len(lines) * line_height
                    start_y = max(0, (OLED_HEIGHT - total_height) // 2)
                    for i, line in enumerate(lines):
                        y_pos = start_y + (i * line_height)
                        x_pos = center_text(line, 16)
                        oled.text(line, x_pos, y_pos)

                    if points_line:
                        points_y = min(OLED_HEIGHT - 8, start_y + total_height + 2)
                        draw_centered_scaled_text(oled, points_line, points_y, scale=1)
            else:
                # Fallback to generic rendering if name is missing
                lines = wrap_text(display_text, width=16)
                max_lines = 3 if OLED_HEIGHT == 32 else 7
                lines = lines[:max_lines]
                line_height = 10
                total_height = len(lines) * line_height
                start_y = max(0, (OLED_HEIGHT - total_height) // 2)
                for i, line in enumerate(lines):
                    y_pos = start_y + (i * line_height)
                    x_pos = center_text(line, 16)
                    oled.text(line, x_pos, y_pos)
        elif display_text.lower().startswith('get ready'):
            action_text = 'Stand Up'
            if 'sit' in display_text.lower():
                action_text = 'Sit Down'
            draw_warning_screen(oled, action_text, time_remaining, warning_animation_frame)
            warning_animation_frame = (warning_animation_frame + 1) % WARNING_ANIMATION_CYCLE
        else:
            # Wrap text to fit display
            lines = wrap_text(display_text, width=16)
            
            # Limit to 3 lines for 32px height (or 7 lines for 64px)
            max_lines = 3 if OLED_HEIGHT == 32 else 7
            lines = lines[:max_lines]
            
            # Calculate vertical centering
            line_height = 10
            total_height = len(lines) * line_height
            start_y = max(0, (OLED_HEIGHT - total_height) // 2)
            
            # Display each line (centered)
            for i, line in enumerate(lines):
                y_pos = start_y + (i * line_height)
                x_pos = center_text(line, 16)
                oled.text(line, x_pos, y_pos)
            warning_animation_frame = 0
        
        # Update display
        oled.show()
        
        last_displayed_message = current_signature
        log("Display updated successfully")
        
    except Exception as e:
        log(f"Display error: {e}")


    # === Display Initialization ===
def init_display():
    """Initialize I2C OLED display"""
    global oled
    
    try:
        log("Initializing I2C OLED display...")
        
        # Setup I2C
        sda = Pin(I2C_SDA_PIN)
        scl = Pin(I2C_SCL_PIN)
        i2c = SoftI2C(sda=sda, scl=scl, freq=I2C_FREQ)
        
        # Scan for devices
        devices = i2c.scan()
        log(f"I2C devices found: {[hex(d) for d in devices]}")
        
        if OLED_ADDR not in devices:
            log(f"Warning: OLED not found at address {hex(OLED_ADDR)}")
        
        # Initialize OLED
        oled = SSD1306_I2C(OLED_WIDTH, OLED_HEIGHT, i2c, addr=OLED_ADDR)
        log(f"OLED initialized: {OLED_WIDTH}x{OLED_HEIGHT}")
        
        # Test display (centered)
        oled.fill(0)
        test_text = "LevelUp!"
        x_pos = center_text(test_text, 16)
        oled.text(test_text, x_pos, 12)
        oled.show()
        time.sleep(1)
        
        return True
        
    except Exception as e:
        log(f"Failed to initialize display: {e}")
        return False


    # === Program Entry Point ===
def main():
    """Main program loop"""
    log("=== LevelUp Pico W Starting ===")
    
    # Initialize display first
    if not init_display():
        log("Cannot continue without display")
        return
    
    # Initialize RGB LED and potentiometer
    init_rgb_led()
    init_potentiometer()
    
    # Initialize pause button and LED
    init_pause_button()
    init_pause_led()
    
    # Make sure we start with correct button state
    if pause_button:
        global last_button_state
        last_button_state = pause_button.value()
        log(f"Initial button state: {last_button_state}")
    
    # Show connecting message (centered)
    oled.fill(0)
    connecting_text = "Connecting..."
    x_pos = center_text(connecting_text, 16)
    oled.text(connecting_text, x_pos, 12)
    oled.show()
    
    # Connect to WiFi
    if not connect_wifi():
        log("Cannot connect to WiFi")
        oled.fill(0)
        oled.text("WiFi Failed!", 15, 8)
        oled.text("Check config", 10, 20)
        oled.show()
        return
    
    # Show ready message (centered)
    oled.fill(0)
    ready_text = "Connected!"
    x_pos = center_text(ready_text, 16)
    oled.text(ready_text, x_pos, 12)
    oled.show()
    time.sleep(1)
    
    log("Starting main loop...")
    
    # Main loop
    retry_count = 0
    max_retries = 3
    last_data_fetch = 0
    
    # Initialize data with fallback
    data = get_display_data()
    
    while True:
        try:
            # Check pause button
            if check_pause_button():
                # Force immediate update on button press
                display_message(data)
            
            # Update LED brightness from potentiometer (fast, non-blocking)
            update_led_brightness()
            
            # Fetch data at regular intervals
            current_time = time.time()
            if current_time - last_data_fetch >= POLL_INTERVAL_SECONDS:
                # Get and display user data
                data = get_display_data()
                display_message(data)
                last_data_fetch = current_time
                
                # Reset retry counter on success
                retry_count = 0
            
            # Small delay to avoid CPU overload but keep brightness responsive
            time.sleep(0.1)
            
        except Exception as e:
            log(f"Loop error: {e}")
            retry_count += 1
            
            if retry_count >= max_retries:
                log("Too many errors, displaying fallback message")
                oled.fill(0)
                error_text = "System Error"
                restart_text = "Restarting..."
                x_pos1 = center_text(error_text, 16)
                x_pos2 = center_text(restart_text, 16)
                oled.text(error_text, x_pos1, 8)
                oled.text(restart_text, x_pos2, 20)
                oled.show()
                time.sleep(5)
                retry_count = 0
            
            time.sleep(2)


# Entry point
if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log("Program stopped by user")
        if oled:
            oled.fill(0)
            stopped_text = "Stopped"
            x_pos = center_text(stopped_text, 16)
            oled.text(stopped_text, x_pos, 12)
            oled.show()
    except Exception as e:
        log(f"Fatal error: {e}")
        if oled:
            oled.fill(0)
            error_text = "Fatal Error"
            x_pos = center_text(error_text, 16)
            oled.text(error_text, x_pos, 12)
            oled.show()
