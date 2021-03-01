#!/usr/bin/env python3

# Imports
import os
import re
import time
import requests
import mysql.connector
from datetime import datetime
from bs4 import BeautifulSoup as bs4
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from PIL import Image

# Current date
date_time   = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

# Create requests session
s = requests.Session()

# Initialize vars
which_request_to_save = 0

try:
	# Get tokens from CAS
	try:
		cas_request = s.get(os.getenv("CAS_URL")+"?service=http%3A%2F%2Funimia.unimi.it%2Fportal%2Fserver.pt", timeout=int(os.getenv("TIMEOUT")))
	except requests.exceptions.Timeout:	# https://github.com/psf/requests/blob/master/requests/exceptions.py
		raise Exception('CAS HTTP request timed out')

	# First request obtained a response
	which_request_to_save = 1

	# Evaluate if obtained response is ok
	if cas_request.status_code!=200:
		raise Exception("CAS status_code is " + str(cas_request.status_code))
	cas_soup = bs4(cas_request.text, 'html.parser')

	# Extract 'lt' token from the response
	lt = cas_soup.find('input', {'name':'lt'})['value']
	if not lt:
		raise Exception('CAS "lt" parameter not found')

	# Extract 'execution' token from the response
	execution = cas_soup.find('input', {'name':'execution'})['value']
	if not execution:
		raise Exception('CAS "execution" parameter not found')

	# CAS auth & redirect to Unimia
	try:
		unimia_request = s.post(os.getenv("CAS_URL"), data = {'username':os.getenv("USERNAME"), 'password':os.getenv("PASSWORD"), 'selTipoUtente':'S', 'lt':lt, 'execution':execution, 'service':'http://unimia.unimi.it/portal/server.pt', '_eventId':'submit'}, timeout=int(os.getenv("TIMEOUT")))
	except requests.exceptions.Timeout:	# https://github.com/psf/requests/blob/master/requests/exceptions.py
		raise Exception('Unimia HTTP request timed out')

	# Second request obtained a response
	which_request_to_save = 2

	# Unimia UP/DOWN evaluation
	unimia_request_lower = unimia_request.text.lower()
	if unimia_request.status_code!=200:
		raise Exception("Unimia status_code is " + str(unimia_request.status_code))
	elif "sqlexception" in unimia_request_lower:
		raise Exception('Page contains "sqlexception"')
	elif "outofmemoryerror" in unimia_request_lower:
		raise Exception('Page contains "outofmemoryerror"')
	elif "i servizi non sono momentaneamente disponibili" in unimia_request_lower:
		raise Exception('Page contains "i servizi non sono momentaneamente disponibili"')
	elif "matricola e' inesistente" in unimia_request_lower:
		raise Exception('Page contains "matricola e\' inesistente"')
	elif "errore di timeout" in unimia_request_lower:
		raise Exception('Page contains "errore di timeout"')
	elif "portlet request timed out" in unimia_request_lower:
		raise Exception('Page contains "portlet request timed out"')
	elif "si è verificato un errore" in unimia_request_lower:
		raise Exception('Page contains "si è verificato un errore"')
	elif "il server remoto ha restituito un codice di risposta http non valido" in unimia_request_lower:
		raise Exception('Page contains "il server remoto ha restituito un codice di risposta http non valido"')
	elif "error 500" in unimia_request_lower:
		raise Exception('Page contains "error 500"')
	elif "http error" in unimia_request_lower:
		raise Exception('Page contains "http error"')
	elif "</strong>0" in unimia_request_lower or "</strong> 0" in unimia_request_lower:
		raise Exception('Page contains "</strong> 0" (indicating 0 CFU)')
	elif "error" in unimia_request_lower:
		raise Exception('Page contains "error"')
	elif "tipo di iscrizione: </strong>in corso" not in unimia_request_lower:
		raise Exception('Page doesn\'t contain "tipo di iscrizione: </strong>in corso"')
	elif "vuoi iscriverti" not in unimia_request_lower:
		raise Exception('Page doesn\'t contain "vuoi iscriverti"')
	elif "dettaglio pagamenti" not in unimia_request_lower:
		raise Exception('Page doesn\'t contain "dettaglio pagamenti"')
	else:
		is_up         = True
		response_time = round(unimia_request.elapsed.total_seconds()*1000)
		reason        = ''
except Exception as e:
		is_up         = False
		response_time = 0
		reason        = re.sub(r"ticket=.{10,50} ", "ticket=REDACTED ", str(e)[0:255], flags=re.IGNORECASE) # Redact CAS ticket from the exception
		print(date_time + ": " + str(e))

# DB Connection
db_conn = mysql.connector.connect(
  host      = os.getenv("MYSQL_HOST"),
  user      = os.getenv("MYSQL_USER"),
  password  = os.getenv("MYSQL_PASSWORD"),
  database  = os.getenv("MYSQL_DATABASE")
)

# Insert data into DB
db_cursor = db_conn.cursor()
db_cursor.execute("INSERT INTO stats (datetime, is_up, response_time, reason) VALUES (%s, %s, %s, %s)", (date_time, is_up, response_time, reason))
db_conn.commit()

# Close cursor and connection to DB
db_cursor.close()
db_conn.close()

# Define private and public path (private path is for debugging purposes and it will contain original unredacted HTML responses corresponding to the saved screenshots of the last month)
private_path    = "/private/"
public_path     = "/screenshot/"

# If it wasn't up and there is a response to save (no HTTP timeout / other HTTP problems)
if not is_up and which_request_to_save != 0:
	# Files (html pages, screenshots...) filenames (without extension) and their absolute paths
	filename        = date_time.replace(":","-").replace(" ","_")
	html_path       = private_path + filename
	screenshot_path = public_path + filename

	# Save HTML response to file
	if which_request_to_save == 1:
		# CAS
		try:
			cas_request.text
			f = open(html_path+".html", "w")
			f.write(cas_request.text)
			f.close()
		except:
			pass
	elif which_request_to_save == 2:
		# Unimia
		try:
			unimia_request.text
			f = open(html_path+".html", "w")
			f.write(unimia_request.text)
			f.close()
		except:
			pass

	# Prepare the browser
	options = Options()
	options.add_argument('--headless')
	options.add_argument('--start-maximized')
	options.add_argument('--no-sandbox')
	options.add_argument('--disable-infobars')
	options.add_argument('--disable-dev-shm-usage')
	options.add_argument("--disable-gpu")
	options.add_argument("--verbose")

	# Open browser and load the saved page
	driver = webdriver.Chrome(options=options)
	try:
		driver.get("file://"+html_path+".html")
		time.sleep(2)

		# Redact elements
		driver.execute_script("""
			// AuthorizationTicket (visibile in case of errors)
			document.body.innerHTML = document.body.innerHTML.replace(/ticket=.{10,50}/ig, 'ticket=REDACTED');
			document.body.innerHTML = document.body.innerHTML.replace(/ticket '.{10,50}'/ig, "Ticket 'REDACTED'");

			// Welcome section ("Home: name surname")
			try {
				document.getElementById("welcome").getElementsByTagName("*")[0].innerText="Home: REDACTED";
			} catch (e) {}

			// "I tuoi dati" section
			try {
				i_tuoi_dati=document.getElementById("div_profilo").getElementsByTagName("li")
				for (i = 0; i < i_tuoi_dati.length; i++) {
					console.log(i_tuoi_dati[i]);
					i_tuoi_dati[i].innerHTML = i_tuoi_dati[i].innerHTML.replace(/(<strong>.+<\/strong>).*/ig, '$1 REDACTED');
				}
			} catch (e) {}

			// TODO: "Esami e opinioni degli studenti" section

			// "Situazione amministrativa" section
			try {
				tasse = document.getElementById("lista-tasse").getElementsByTagName("td")
				for (i = 0; i < tasse.length; i++) {
					if ([2, 3, 4, 5, 8, 9, 10, 11, 14, 15, 16, 17].includes(i)) {
						tasse[i].innerText='REDACTED';
					}
				}
			} catch (e) {}

			// "I tuoi siti didattici" sidebar
			try {
				document.getElementById("div_siti_aperto").innerText="REDACTED";
			} catch (e) {}

			// "CFU / Carriera" section
			document.body.innerHTML = document.body.innerHTML.replace(/Dettaglio carriera ?- ?<\/strong>.+<\/p>/ig, 'Dettaglio carriera - </strong>REDACTED</p>');
			document.body.innerHTML = document.body.innerHTML.replace(/Esami registrati \(in piano\):<\/strong> ?(\d*)(\.\d+)?/ig, 'Esami registrati (in piano):</strong> REDACTED');
			document.body.innerHTML = document.body.innerHTML.replace(/Media dei voti \(esami in piano\):<\/strong> ?(\d*)(\.\d+)?/ig, 'Media dei voti (esami in piano):</strong> REDACTED');
			document.body.innerHTML = document.body.innerHTML.replace(/CFU totali \(esami e altre attività in piano e fuori piano\):<\/strong> ?(\d*)(\.\d+)?/ig, 'CFU totali (esami e altre attività in piano e fuori piano):</strong> REDACTED');
		""")
		blacklist = os.getenv("WORD_BLACKLIST").split(',')
		for b in blacklist:
			driver.execute_script("document.body.innerHTML = document.body.innerHTML.replace(new RegExp('"+b.replace("'", "\\'")+"', 'ig'), 'REDACTED');")
		time.sleep(2)

		# Set the page size
		width  = driver.execute_script('return document.body.parentNode.scrollWidth') + 20
		height = driver.execute_script('return document.body.parentNode.scrollHeight') + 20
		driver.set_window_size(width, height)
		time.sleep(2)

		# Save a PNG screenshot of the page
		driver.save_screenshot(screenshot_path+".png")

	finally:
		# Close the browser
		driver.quit()

	# Convert saved PNG to JPG (and reduce its size to 2/3 to save space)
	original_image = Image.open(screenshot_path+".png")
	original_image.resize( (round(original_image.size[0]/3*2), round(original_image.size[1]/3*2)), Image.ANTIALIAS ).convert('RGB').save(screenshot_path+".jpg", optimize=True)

	# Remove the original PNG file
	os.remove(screenshot_path+".png")

# Delete saved HTML pages older than 30 days
for f in os.listdir(private_path):
	f = os.path.join(private_path, f)
	if os.stat(f).st_mtime < time.time() - 30 * 86400 and os.path.isfile(f):
		os.remove(f)
