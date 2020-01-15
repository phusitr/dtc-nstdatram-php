const mqtt = require('mqtt');
const http = require('http')

const client = mqtt.connect('mqtt://www.xxx.in.th:1883');

const mqtt_topic =
['tramLocation/003-0002-nbus'];
client.on('connect', () => {
    for (i=0; i<mqtt_topic.length; i++){
        client.subscribe(mqtt_topic[i]);
        console.log(mqtt_topic[i]);
    }
});

client.on("error", console.error);

client.on('message', (topic, message) => {
  let data = JSON.parse(message);
  console.log(topic)
  console.log(data)
});


