FROM docker.io/bitnami/laravel:8
WORKDIR /app
USER bitnami
RUN mkdir -p bootstrap/cache 
RUN mkdir storage
USER root
RUN install_packages zip unzip php-zip
USER bitnami
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views
COPY . .
COPY ./startup.sh /tmp
RUN ls
USER root
RUN chmod 777 /tmp/startup.sh
ENV APP_ENV=development
USER bitnami
ENTRYPOINT [ "/tmp/startup.sh" ]
# USER root
# RUN ["chmod", "0777", "meetrun.sh"]
# CMD ["./meetrun.sh"]