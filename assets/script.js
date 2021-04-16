const user = {
    name: ''
};
const chat = document.getElementById('chat');
const message = document.getElementById('message');
const name = document.getElementById('name');
const enter = document.getElementById('enter');
const users = document.getElementById('users');

const socket = new WebSocket('ws://localhost:9990/chat');

// Ao receber mensagens do servidor
socket.addEventListener('message', function (event) {
    // Deserializamos o objeto
    const data = JSON.parse(event.data);
    switch (data.type) {
        case 'users':
            users.innerHTML = '<h6>Online</h6>';
            for (let key in data.value) {
                let u = data.value[key];
                let aux = document.createElement('div');
                aux.classList.add('user');
                aux.innerHTML = `<div class="avatar"> <img src="https://avatars.dicebear.com/api/avataaars/${u.name}.svg" alt="${u.name}"><div class="status online"></div></div><div class="name">${u.name}</div><div class="mood">User mood</div></div>`
                users.appendChild(aux);
            }
            break;
        case 'leave':
            break;
        case 'enter':
            break;
        case 'message':
            chat.insertAdjacentHTML('beforeend', "<p><b>" + data.user.name + " diz: </b>" + data.value + "</p>");
            break;
    }
});

// Ao enviar uma mensagem
message.addEventListener('keyup', function (event) {
    if (event.keyCode === 13) {
        // Objeto com os dados que serão trafegados
        const messageData = {
            type: 'message',
            user: user,
            value: this.value,
        };
        // Serializamos o objeto para json
        socket.send(JSON.stringify(messageData));
        this.value = '';
    }
});

enter.addEventListener('click', function (event) {
    user.name = name.value;
    const enterData = {
        type: 'enter',
        user: user,
        value: null
    };
    socket.send(JSON.stringify(enterData));
    $('#staticBackdrop').modal('hide');
});

$('#staticBackdrop').modal({});