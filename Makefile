ARCHIVENAME = frisbee-payment-gateway.zip

build:
	rm -f "$(ARCHIVENAME)"
	mkdir frisbee.frisbee
	cp -r ./install/ frisbee.frisbee/install/
	cp -r ./lang/ frisbee.frisbee/lang/
	cp include.php frisbee.frisbee
	cp README.md frisbee.frisbee
	zip -r "$(ARCHIVENAME)" frisbee.frisbee
	rm -rf frisbee.frisbee

