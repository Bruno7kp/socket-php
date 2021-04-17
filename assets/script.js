new Vue({
    el: '#app',
    data() {
        const user = {
            name: '',
            status: 'online',
            uid: '_' + Math.random().toString(36).substr(2, 9),
        };

        return {
            user: user,
            usersList: [],
            socket: null,
            enterButton: true,
            chats: {
                'group': [],
            },
            chat: 'group',
            messages: [],
            message: '',
        }
    },
    methods: {
        enter() {
            if (this.user.name.trim().length === 0) {
                alert('Digite seu nome de usuário');
                return;
            }

            this.setupSocket();
            this.enterButton = false;
        },
        leave() {
            this.socket.close();
            this.enterButton = true;
        },
        send(data) {
            this.socket.send(JSON.stringify(data));
        },
        sendMessage() {
            if (this.message.trim().length === 0) {
                alert('Escreva alguma mensagem.');
            }
            let to = null;
            if (this.chat !== 'group') {
                to = this.getUser(this.chat);
            }
            const messageData = {
                type: 'message',
                user: this.user,
                value: this.message,
                to: to,
                date: Date.now(),
            };
            // Serializamos o objeto para json
            this.socket.send(JSON.stringify(messageData));
            this.message = '';
        },
        setChat(chat) {
            this.chat = chat;
            this.message = '';
            this.messages = this.chats[chat];
        },
        getUser(uid) {
            for(let i = 0; i < this.usersList.length; i++) {
                if (this.usersList[i].uid === uid) {
                    return this.usersList[i];
                }
            }
            return null;
        },
        getUserName(uid) {
            let u = this.getUser(uid);
            if (u) {
                return u.name;
            }
            return null;
        },
        setupSocket() {
            this.socket = new WebSocket('ws://localhost:9990/chat');
            this.socket.onopen = () => {
                const enterData = {
                    type: 'enter',
                    user: this.user,
                    value: null,
                    to: null,
                    date: Date.now(),
                };
                this.send(enterData);
            };

            this.socket.addEventListener('message', (event) => {
                // Deserializamos o objeto
                const data = JSON.parse(event.data);
                switch (data.type) {
                    case 'users':
                        this.usersList = data.value;
                        for (let i = 0; i < this.usersList.length; i++) {
                            if (typeof this.chats[this.usersList[i].uid] === 'undefined') {
                                this.chats[this.usersList[i].uid] = [];
                            }
                        }
                        break;
                    case 'leave':
                        break;
                    case 'enter':
                        break;
                    case 'message':
                        let chatid = 'group';
                        if (data.to === null) {
                            this.chats['group'].push(data);
                            chatid = 'group';
                        } else {
                            if (data.user.uid === this.user.uid) {
                                this.chats[data.to.uid].push(data);
                                chatid = data.to.uid;
                            } else {
                                this.chats[data.user.uid].push(data);
                                chatid = data.user.uid;
                            }
                        }
                        if (chatid === this.chat) {
                            this.messages = this.chats[chatid];
                        }
                        break;
                }
            });
        },
    },
});

Vue.use(VueTimeago, {
    locale: 'pt-BR',
    locales: {
        'pt-BR': [
            "agora mesmo",
            ["%s segundo atrás", "%s segundos atrás"],
            ["%s minuto atrás", "%s minutos atrás"],
            ["%s hora atrás", "%s horas atrás"],
            ["%s dia atrás", "%s dias atrás"],
            ["%s semana atrás", "%s semanas atrás"],
            ["%s mês atrás", "%s meses atrás"],
            ["%s ano atrás", "%s anos atrás"]
        ],
    }
});
Vue.config.devtools = true;
