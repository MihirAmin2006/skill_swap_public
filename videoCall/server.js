const express = require("express");
const http = require("http");
const { Server } = require("socket.io");

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: "*", methods: ["GET", "POST"], credentials: true }
});

app.use(express.static("public"));

const rooms = {};

io.on("connection", (socket) => {
  console.log("[connect]", socket.id);

  // ---- JOIN ROOM ----
  socket.on("join-room", (roomId) => {
    if (!rooms[roomId]) rooms[roomId] = [];

    if (rooms[roomId].length >= 2) {
      socket.emit("room-full");
      return;
    }

    rooms[roomId].push(socket.id);
    socket.join(roomId);
    console.log(`[join-room] ${socket.id} → room ${roomId} (${rooms[roomId].length}/2)`);

    if (rooms[roomId].length === 2) {
      io.to(rooms[roomId][0]).emit("initiate-call");
    }

    socket.on("disconnect", () => {
      if (rooms[roomId]) {
        rooms[roomId] = rooms[roomId].filter(id => id !== socket.id);
        socket.to(roomId).emit("user-disconnected");
        console.log(`[disconnect] ${socket.id} left room ${roomId}`);
        if (rooms[roomId].length === 0) delete rooms[roomId];
      }
    });
  });

  // ---- WebRTC SIGNALING ----
  socket.on("offer",         (data) => socket.to(data.room).emit("offer",         data.offer));
  socket.on("answer",        (data) => socket.to(data.room).emit("answer",        data.answer));
  socket.on("ice-candidate", (data) => socket.to(data.room).emit("ice-candidate", data.candidate));

  // ---- IN-CALL CHAT ----
  socket.on("chat-message", (data) => {
    socket.to(data.room).emit("chat-message", {
      name: data.name,
      text: data.text,
      time: data.time
    });
  });

  // ---- WHITEBOARD ----
  socket.on("whiteboard-draw",  (data) => socket.to(data.room).emit("whiteboard-draw",  data));
  socket.on("whiteboard-line",  (data) => socket.to(data.room).emit("whiteboard-line",  data));
  socket.on("whiteboard-clear", (data) => socket.to(data.room).emit("whiteboard-clear"));
  socket.on("whiteboard-open",  (data) => socket.to(data.room).emit("whiteboard-open"));
  socket.on("whiteboard-close", (data) => socket.to(data.room).emit("whiteboard-close"));

  // ---- SCREEN SHARE ----
  socket.on("screen-share-start", (data) => socket.to(data.room).emit("screen-share-start"));
  socket.on("screen-share-stop",  (data) => socket.to(data.room).emit("screen-share-stop"));
});

server.listen(3000, () => {
  console.log("Server running at https://nfr7183k-3000.inc1.devtunnels.ms/");
});